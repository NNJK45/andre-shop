<?php

namespace Tests\Feature;

use App\Application\Payment\PaymentService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_routes_require_authentication(): void
    {
        $this->postJson('/api/customer/orders/ORD-UNKNOWN/payments')->assertUnauthorized();
        $this->getJson('/api/customer/payments/PAY-UNKNOWN')->assertUnauthorized();
    }

    public function test_customer_can_initialize_payment_for_their_pending_order(): void
    {
        [$customer, $token] = $this->customer();
        $order = $this->order($customer, 12500);

        $this->withToken($token)
            ->postJson("/api/customer/orders/{$order->number}/payments", [], [
                'Idempotency-Key' => 'checkout-attempt-001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.amount', '12500.00')
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonPath('data.provider', 'nokash')
            ->assertJsonPath('data.provider_reference', fn ($value) => str_starts_with($value, 'FAKE-'))
            ->assertJsonPath('data.checkout_url', fn ($value) => str_starts_with($value, 'https://payments.example.test/'));

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'idempotency_key' => 'checkout-attempt-001',
            'status' => PaymentStatus::Pending->value,
            'amount' => 12500,
        ]);
    }

    public function test_payment_initialization_is_idempotent(): void
    {
        [$customer, $token] = $this->customer();
        $order = $this->order($customer, 5000);
        $headers = ['Idempotency-Key' => 'same-attempt'];

        $firstReference = $this->withToken($token)
            ->postJson("/api/customer/orders/{$order->number}/payments", [], $headers)
            ->json('data.reference');

        $secondReference = $this->withToken($token)
            ->postJson("/api/customer/orders/{$order->number}/payments", [], $headers)
            ->assertCreated()
            ->json('data.reference');

        $this->assertSame($firstReference, $secondReference);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_customer_cannot_pay_or_view_another_customers_payment(): void
    {
        [$owner, $ownerToken] = $this->customer();
        $order = $this->order($owner, 5000);
        $reference = $this->withToken($ownerToken)
            ->postJson("/api/customer/orders/{$order->number}/payments")
            ->json('data.reference');

        [, $otherToken] = $this->customer();
        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)
            ->postJson("/api/customer/orders/{$order->number}/payments")
            ->assertNotFound();

        $this->withToken($otherToken)
            ->getJson("/api/customer/payments/{$reference}")
            ->assertNotFound();
    }

    public function test_only_pending_payment_orders_can_initialize_a_payment(): void
    {
        [$customer, $token] = $this->customer();
        $order = $this->order($customer, 5000, OrderStatus::Paid);

        $this->withToken($token)
            ->postJson("/api/customer/orders/{$order->number}/payments")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_successful_confirmation_marks_payment_and_order_once(): void
    {
        [$customer, $token] = $this->customer();
        $order = $this->order($customer, 8000);
        $reference = $this->withToken($token)
            ->postJson("/api/customer/orders/{$order->number}/payments")
            ->json('data.reference');
        $payment = Payment::query()->where('reference', $reference)->firstOrFail();
        $service = app(PaymentService::class);

        $service->markSucceeded($payment, 'NOKASH-TXN-001');
        $service->markSucceeded($payment->refresh(), 'NOKASH-TXN-001');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'provider_reference' => 'NOKASH-TXN-001',
            'status' => PaymentStatus::Succeeded->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Paid->value,
        ]);
        $this->assertDatabaseCount('order_status_histories', 1);
        $this->assertDatabaseHas('order_status_histories', [
            'from_status' => OrderStatus::PendingPayment->value,
            'to_status' => OrderStatus::Paid->value,
            'changed_by_user_id' => null,
        ]);
    }

    public function test_late_success_can_recover_a_failed_payment(): void
    {
        [$customer, $token] = $this->customer();
        $order = $this->order($customer, 8000);
        $reference = $this->withToken($token)
            ->postJson("/api/customer/orders/{$order->number}/payments")
            ->json('data.reference');
        $payment = Payment::query()->where('reference', $reference)->firstOrFail();
        $service = app(PaymentService::class);

        $service->markFailed($payment);
        $this->assertSame(PaymentStatus::Failed, $payment->refresh()->status);

        $service->markSucceeded($payment, 'NOKASH-LATE-001');

        $this->assertSame(PaymentStatus::Succeeded, $payment->refresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_admin_can_list_payments_but_customer_cannot(): void
    {
        [$customer, $customerToken] = $this->customer();
        $order = $this->order($customer, 5000);

        $this->withToken($customerToken)
            ->postJson("/api/customer/orders/{$order->number}/payments")
            ->assertCreated();

        $this->withToken($customerToken)
            ->getJson('/api/admin/payments')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->withToken($this->adminToken())
            ->getJson('/api/admin/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    private function order(
        User $customer,
        float $total,
        OrderStatus $status = OrderStatus::PendingPayment,
    ): Order {
        return Order::query()->create([
            'user_id' => $customer->id,
            'number' => 'ORD-'.strtoupper(str()->random(12)),
            'status' => $status,
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

    private function adminToken(): string
    {
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        return $admin->createToken('admin')->plainTextToken;
    }
}
