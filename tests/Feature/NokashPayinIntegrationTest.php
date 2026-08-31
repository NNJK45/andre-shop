<?php

namespace Tests\Feature;

use App\Application\Payment\Contracts\PaymentGateway;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NokashPayinIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.driver' => 'nokash',
            'payments.nokash.i_space_key' => 'test-i-space-key',
            'payments.nokash.app_space_key' => 'test-app-space-key',
            'payments.nokash.callback_url' => 'https://shop.test/api/webhooks/nokash',
        ]);
        $this->app->forgetInstance(PaymentGateway::class);
    }

    protected function tearDown(): void
    {
        config(['payments.driver' => 'fake']);
        $this->app->forgetInstance(PaymentGateway::class);
        parent::tearDown();
    }

    public function test_customer_can_initialize_a_nokash_payin(): void
    {
        $payinUrl = config('payments.nokash.payin_url');
        Http::fake([
            $payinUrl => Http::response([
                'status' => 'REQUEST_OK',
                'message' => 'Request accepted',
                'data' => [
                    'id' => 'NK-PAYIN-001',
                    'status' => 'PENDING',
                    'amount' => 12500,
                    'orderId' => 'PAY-REFERENCE',
                    'phone' => '237690000000',
                ],
            ], 200),
        ]);

        [$customer, $token] = $this->customer();
        $order = $this->order($customer, 12500);

        $response = $this->withToken($token)->postJson(
            "/api/customer/orders/{$order->number}/payments",
            [
                'payment_method' => 'MTN_MOMO',
                'user_phone' => '+237690000000',
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.provider_reference', 'NK-PAYIN-001');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider_reference' => 'NK-PAYIN-001',
            'provider' => 'nokash',
            'amount' => 12500,
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === config('payments.nokash.payin_url')
                && $request['i_space_key'] === 'test-i-space-key'
                && $request['app_space_key'] === 'test-app-space-key'
                && $request['payment_type'] === 'CM_MOBILEMONEY'
                && $request['country'] === 'CM'
                && $request['payment_method'] === 'MTN_MOMO'
                && (float) $request['amount'] === 12500.0
                && $request['user_data']['user_phone'] === '+237690000000'
                && $request['callback_url'] === 'https://shop.test/api/webhooks/nokash';
        });
    }

    public function test_nokash_callback_revalidates_status_and_confirms_order_idempotently(): void
    {
        $payinUrl = config('payments.nokash.payin_url');
        $statusUrl = config('payments.nokash.status_url');

        Http::fake([
            $payinUrl => Http::response([
                'status' => 'REQUEST_OK',
                'data' => [
                    'id' => 'NK-PAYIN-002',
                    'status' => 'PENDING',
                    'amount' => 8000,
                    'orderId' => 'PAY-REFERENCE',
                    'phone' => '237690000000',
                ],
            ]),
        ]);

        [$customer, $token] = $this->customer();
        $order = $this->order($customer, 8000);
        $reference = $this->withToken($token)->postJson(
            "/api/customer/orders/{$order->number}/payments",
            [
                'payment_method' => 'ORANGE_MONEY',
                'user_phone' => '237690000000',
            ],
        )->json('data.reference');
        $payment = Payment::query()->where('reference', $reference)->firstOrFail();

        Http::fake([
            $statusUrl => Http::response([
                'status' => 'REQUEST_OK',
                'message' => 'Transaction found',
                'data' => [
                    'id' => 'NK-PAYIN-002',
                    'status' => 'SUCCESS',
                    'amount' => 8000,
                    'orderId' => $payment->reference,
                    'phone' => '237690000000',
                ],
            ]),
        ]);

        $callback = [
            'id' => 'NK-PAYIN-002',
            'status' => 'SUCCESS',
            'amount' => 8000,
            'phone' => '237690000000',
            'orderId' => $payment->reference,
        ];

        $this->postJson('/api/webhooks/nokash', $callback)
            ->assertOk()
            ->assertJson([
                'status' => 'accepted',
                'payment_status' => PaymentStatus::Succeeded->value,
            ]);

        $this->postJson('/api/webhooks/nokash', $callback)->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Succeeded->value,
            'paid_at' => $payment->refresh()->paid_at,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Paid->value,
        ]);
        $this->assertDatabaseCount('payment_events', 1);
        Http::assertSentCount(2);
    }

    public function test_nokash_callback_is_rejected_when_amount_does_not_match_verified_status(): void
    {
        $statusUrl = config('payments.nokash.status_url');
        [$customer, $token] = $this->customer();
        $order = $this->order($customer, 5000);

        Http::fake([
            config('payments.nokash.payin_url') => Http::response([
                'status' => 'REQUEST_OK',
                'data' => [
                    'id' => 'NK-PAYIN-003',
                    'status' => 'PENDING',
                    'amount' => 5000,
                    'orderId' => 'PAY-REFERENCE',
                ],
            ]),
        ]);

        $reference = $this->withToken($token)->postJson(
            "/api/customer/orders/{$order->number}/payments",
            ['payment_method' => 'MTN_MOMO', 'user_phone' => '237690000000'],
        )->json('data.reference');

        Http::fake([
            $statusUrl => Http::response([
                'status' => 'REQUEST_OK',
                'data' => [
                    'id' => 'NK-PAYIN-003',
                    'status' => 'SUCCESS',
                    'amount' => 5000,
                    'orderId' => $reference,
                ],
            ]),
        ]);

        $this->postJson('/api/webhooks/nokash', [
            'id' => 'NK-PAYIN-003',
            'status' => 'SUCCESS',
            'amount' => 4999,
            'orderId' => $reference,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('payments', [
            'reference' => $reference,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    private function order(User $customer, float $total): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'number' => 'ORD-'.strtoupper(str()->random(12)),
            'status' => OrderStatus::PendingPayment,
            'subtotal' => $total,
            'total' => $total,
            'currency' => 'XAF',
            'shipping_address' => [
                'full_name' => $customer->name,
                'phone' => '+237600000000',
                'line1' => '123 Commerce Street',
                'city' => 'Douala',
                'country_code' => 'CM',
            ],
            'placed_at' => now(),
        ]);
    }

    private function customer(): array
    {
        $customer = User::factory()->create();

        return [$customer, $customer->createToken('customer')->plainTextToken];
    }
}
