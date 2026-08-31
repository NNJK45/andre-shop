<?php

namespace Tests\Feature;

use App\Domain\Delivery\Enums\DeliveryStatus;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_track_delivery_until_order_is_delivered(): void
    {
        $customer = User::factory()->create();
        $adminToken = $this->adminToken();
        $customerToken = $customer->createToken('customer')->plainTextToken;
        $order = $this->order($customer, OrderStatus::Shipped);

        $response = $this->withToken($adminToken)->postJson('/api/admin/deliveries', [
            'order_id' => $order->id,
            'provider' => 'DHL',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', DeliveryStatus::Pending->value)
            ->assertJsonPath('data.provider', 'DHL');

        $tracking = $response->json('data.tracking_number');

        foreach ([
            DeliveryStatus::Assigned,
            DeliveryStatus::PickedUp,
            DeliveryStatus::InTransit,
            DeliveryStatus::Delivered,
        ] as $status) {
            $this->withToken($adminToken)
                ->patchJson("/api/admin/deliveries/{$tracking}/status", ['status' => $status->value])
                ->assertOk()
                ->assertJsonPath('data.status', $status->value);
        }

        $this->assertDatabaseHas('deliveries', [
            'tracking_number' => $tracking,
            'status' => DeliveryStatus::Delivered->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Delivered->value,
        ]);
        $this->assertDatabaseCount('notifications', 4);

        $this->app['auth']->forgetGuards();
        $this->withToken($customerToken)
            ->getJson("/api/customer/orders/{$order->number}/delivery")
            ->assertOk()
            ->assertJsonPath('data.tracking_number', $tracking)
            ->assertJsonPath('data.status', DeliveryStatus::Delivered->value);

        $this->app['auth']->forgetGuards();
        $notification = $this->withToken($customerToken)
            ->getJson('/api/customer/notifications')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->json('data.0.id');

        $this->app['auth']->forgetGuards();
        $this->withToken($customerToken)
            ->patchJson("/api/customer/notifications/{$notification}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($value) => $value !== null);
    }

    public function test_delivery_cannot_be_created_for_an_unpaid_order(): void
    {
        $adminToken = $this->adminToken();
        $customer = User::factory()->create();
        $order = $this->order($customer, OrderStatus::PendingPayment);

        $this->withToken($adminToken)
            ->postJson('/api/admin/deliveries', ['order_id' => $order->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_id');

        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_delivery_and_notifications_are_private_to_the_customer(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerToken = $owner->createToken('owner')->plainTextToken;
        $otherToken = $other->createToken('other')->plainTextToken;
        $order = $this->order($owner, OrderStatus::Shipped);

        Delivery::query()->create([
            'order_id' => $order->id,
            'tracking_number' => 'DLV-PRIVATE-001',
            'status' => DeliveryStatus::Pending,
            'recipient_name' => $owner->name,
            'recipient_phone' => '+237690000000',
            'recipient_address' => $order->shipping_address,
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)
            ->getJson("/api/customer/orders/{$order->number}/delivery")
            ->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->withToken($ownerToken)
            ->getJson('/api/customer/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function order(User $customer, OrderStatus $status): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'number' => 'ORD-'.strtoupper(str()->random(12)),
            'status' => $status,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'XAF',
            'shipping_address' => [
                'full_name' => $customer->name,
                'phone' => '+237690000000',
                'line1' => '1 Rue du Commerce',
                'city' => 'Douala',
                'country_code' => 'CM',
            ],
            'placed_at' => now(),
        ]);
    }

    private function adminToken(): string
    {
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        return $admin->createToken('admin')->plainTextToken;
    }
}
