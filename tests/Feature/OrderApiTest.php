<?php

namespace Tests\Feature;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_routes_require_authentication_and_non_empty_cart(): void
    {
        $this->getJson('/api/customer/orders')->assertUnauthorized();
        $this->postJson('/api/customer/orders', [])->assertUnauthorized();

        [$customer, $token] = $this->customer();

        $this->withToken($token)
            ->postJson('/api/customer/orders', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_customer_can_checkout_cart_atomically(): void
    {
        [$customer, $token] = $this->customer();
        [$product, $inventory] = $this->stockedProduct(10, 125);

        $this->withToken($token)
            ->postJson('/api/customer/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertOk();

        $response = $this->withToken($token)
            ->postJson('/api/customer/orders', $this->checkoutPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('data.subtotal', '250.00')
            ->assertJsonPath('data.total', '250.00')
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonPath('data.shipping_address.city', 'Douala')
            ->assertJsonPath('data.billing_address.city', 'Douala')
            ->assertJsonPath('data.items.0.name', 'Test Product')
            ->assertJsonPath('data.items.0.sku', 'SKU-001')
            ->assertJsonPath('data.items.0.quantity', 2);

        $number = $response->json('data.number');

        $this->assertDatabaseHas('orders', [
            'user_id' => $customer->id,
            'number' => $number,
            'status' => OrderStatus::PendingPayment->value,
        ]);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'on_hand' => 8,
            'reserved' => 0,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'type' => StockMovementType::Sale->value,
            'on_hand_delta' => -2,
            'reserved_delta' => -2,
            'reference' => $number,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'to_status' => OrderStatus::PendingPayment->value,
        ]);
    }

    public function test_checkout_rolls_back_when_reserved_stock_is_inconsistent(): void
    {
        [$customer, $token] = $this->customer();
        [$product, $inventory] = $this->stockedProduct(5, 50);
        $cart = Cart::query()->create([
            'user_id' => $customer->id,
            'expires_at' => now()->addDays(7),
        ]);
        $item = new CartItem(['quantity' => 3, 'unit_price' => 50]);
        $item->purchasable()->associate($product);
        $cart->items()->save($item);
        $inventory->update(['reserved' => 2]);

        $this->withToken($token)
            ->postJson('/api/customer/orders', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'on_hand' => 5,
            'reserved' => 2,
        ]);
    }

    public function test_customer_only_sees_their_own_orders(): void
    {
        [$firstCustomer, $firstToken] = $this->customer();
        [$product] = $this->stockedProduct(5, 30);

        $this->withToken($firstToken)
            ->postJson('/api/customer/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $orderNumber = $this->withToken($firstToken)
            ->postJson('/api/customer/orders', $this->checkoutPayload())
            ->json('data.number');

        [, $secondToken] = $this->customer();
        $this->app['auth']->forgetGuards();

        $this->withToken($secondToken)
            ->getJson('/api/customer/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withToken($secondToken)
            ->getJson("/api/customer/orders/{$orderNumber}")
            ->assertNotFound();

        $this->assertDatabaseHas('orders', [
            'number' => $orderNumber,
            'user_id' => $firstCustomer->id,
        ]);
    }

    public function test_admin_can_cancel_an_order_and_stock_is_returned_once(): void
    {
        [, $customerToken] = $this->customer();
        [$product, $inventory] = $this->stockedProduct(10, 75);

        $this->withToken($customerToken)
            ->postJson('/api/customer/cart/items', [
                'product_id' => $product->id,
                'quantity' => 3,
            ]);

        $number = $this->withToken($customerToken)
            ->postJson('/api/customer/orders', $this->checkoutPayload())
            ->json('data.number');

        $this->app['auth']->forgetGuards();
        $adminToken = $this->adminToken();

        $this->withToken($adminToken)
            ->patchJson("/api/admin/orders/{$number}/status", [
                'status' => OrderStatus::Cancelled->value,
                'note' => 'Customer request',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'on_hand' => 10,
            'reserved' => 0,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'type' => StockMovementType::Return->value,
            'on_hand_delta' => 3,
            'reference' => $number,
        ]);

        $this->withToken($adminToken)
            ->patchJson("/api/admin/orders/{$number}/status", [
                'status' => OrderStatus::Cancelled->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'on_hand' => 10,
        ]);
    }

    public function test_admin_status_transitions_follow_the_order_workflow(): void
    {
        [, $customerToken] = $this->customer();
        [$product] = $this->stockedProduct(5, 20);

        $this->withToken($customerToken)
            ->postJson('/api/customer/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
        $number = $this->withToken($customerToken)
            ->postJson('/api/customer/orders', $this->checkoutPayload())
            ->json('data.number');

        $this->app['auth']->forgetGuards();
        $adminToken = $this->adminToken();

        foreach ([
            OrderStatus::Paid,
            OrderStatus::Processing,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
        ] as $status) {
            $this->withToken($adminToken)
                ->patchJson("/api/admin/orders/{$number}/status", [
                    'status' => $status->value,
                ])
                ->assertOk()
                ->assertJsonPath('data.status', $status->value);
        }

        $this->assertDatabaseCount('order_status_histories', 5);
    }

    private function checkoutPayload(): array
    {
        return [
            'shipping_address' => [
                'full_name' => 'André Client',
                'phone' => '+237600000000',
                'line1' => '123 Commerce Street',
                'city' => 'Douala',
                'region' => 'Littoral',
                'country_code' => 'CM',
            ],
        ];
    }

    private function stockedProduct(int $onHand, float $price): array
    {
        $product = Product::query()->create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'SKU-001',
            'price' => $price,
        ]);
        $inventory = new InventoryItem(['on_hand' => $onHand]);
        $inventory->stockable()->associate($product);
        $inventory->save();

        return [$product, $inventory];
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
