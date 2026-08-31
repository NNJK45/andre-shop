<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_routes_require_authentication(): void
    {
        $this->getJson('/api/customer/cart')->assertUnauthorized();
        $this->postJson('/api/customer/cart/items', [])->assertUnauthorized();
        $this->deleteJson('/api/customer/cart')->assertUnauthorized();
    }

    public function test_authenticated_customer_can_view_an_empty_cart(): void
    {
        $this->withToken($this->customerToken())
            ->getJson('/api/customer/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.total', '0.00')
            ->assertJsonStructure(['data' => ['expires_at']]);
    }

    public function test_adding_and_updating_an_item_keeps_inventory_reservation_in_sync(): void
    {
        [$product, $inventory] = $this->stockedProduct(10, 100);
        $token = $this->customerToken();

        $itemId = $this->withToken($token)
            ->postJson('/api/customer/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.sku', $product->sku)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.subtotal', '200.00')
            ->assertJsonPath('data.total', '200.00')
            ->json('data.items.0.id');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'reserved' => 2,
        ]);

        $this->withToken($token)
            ->postJson('/api/customer/cart/items', [
                'product_id' => $product->id,
                'quantity' => 3,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 5)
            ->assertJsonPath('data.items_count', 5);

        $this->withToken($token)
            ->patchJson("/api/customer/cart/items/{$itemId}", ['quantity' => 1])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.total', '100.00');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'reserved' => 1,
        ]);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_variant_uses_its_price_or_falls_back_to_product_price(): void
    {
        [$product] = $this->stockedProduct(10, 80);
        $variant = $product->variants()->create([
            'name' => 'Premium',
            'sku' => 'SKU-001-P',
            'price' => 95,
        ]);
        $inventory = new InventoryItem(['on_hand' => 5]);
        $inventory->stockable()->associate($variant);
        $inventory->save();

        $this->withToken($this->customerToken())
            ->postJson('/api/customer/cart/items', [
                'variant_id' => $variant->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.type', 'variant')
            ->assertJsonPath('data.items.0.unit_price', '95.00')
            ->assertJsonPath('data.total', '190.00');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'reserved' => 2,
        ]);
    }

    public function test_cart_rejects_unavailable_or_uninitialized_stock_atomically(): void
    {
        [$product, $inventory] = $this->stockedProduct(2, 50);
        $token = $this->customerToken();

        $this->withToken($token)
            ->postJson('/api/customer/cart/items', [
                'product_id' => $product->id,
                'quantity' => 3,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $withoutInventory = Product::query()->create([
            'name' => 'No Inventory',
            'slug' => 'no-inventory',
            'sku' => 'NO-INV',
            'price' => 25,
        ]);

        $this->withToken($token)
            ->postJson('/api/customer/cart/items', [
                'product_id' => $withoutInventory->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stock');

        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'reserved' => 0,
        ]);
    }

    public function test_customer_cannot_modify_another_customers_cart_item(): void
    {
        [$product] = $this->stockedProduct(10, 50);
        $firstToken = $this->customerToken();
        $secondToken = $this->customerToken();

        $itemId = $this->withToken($firstToken)
            ->postJson('/api/customer/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->json('data.items.0.id');

        $this->app['auth']->forgetGuards();

        $this->withToken($secondToken)
            ->patchJson("/api/customer/cart/items/{$itemId}", ['quantity' => 1])
            ->assertNotFound();

        $this->withToken($secondToken)
            ->deleteJson("/api/customer/cart/items/{$itemId}")
            ->assertNotFound();

        $this->assertDatabaseHas('cart_items', [
            'id' => $itemId,
            'quantity' => 2,
        ]);
    }

    public function test_removing_and_clearing_items_release_all_stock(): void
    {
        [$firstProduct, $firstInventory] = $this->stockedProduct(10, 30, 'First', 'FIRST');
        [$secondProduct, $secondInventory] = $this->stockedProduct(10, 40, 'Second', 'SECOND');
        $token = $this->customerToken();

        $firstItemId = $this->withToken($token)
            ->postJson('/api/customer/cart/items', [
                'product_id' => $firstProduct->id,
                'quantity' => 2,
            ])
            ->json('data.items.0.id');

        $this->withToken($token)
            ->postJson('/api/customer/cart/items', [
                'product_id' => $secondProduct->id,
                'quantity' => 3,
            ])
            ->assertOk();

        $this->withToken($token)
            ->deleteJson("/api/customer/cart/items/{$firstItemId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $firstInventory->id,
            'reserved' => 0,
        ]);

        $this->withToken($token)
            ->deleteJson('/api/customer/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.total', '0.00');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $secondInventory->id,
            'reserved' => 0,
        ]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    private function stockedProduct(
        int $onHand,
        float $price,
        string $name = 'Test Product',
        string $sku = 'SKU-001',
    ): array {
        $product = Product::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'sku' => $sku,
            'price' => $price,
        ]);

        $inventory = new InventoryItem(['on_hand' => $onHand]);
        $inventory->stockable()->associate($product);
        $inventory->save();

        return [$product, $inventory];
    }

    private function customerToken(): string
    {
        $customer = User::factory()->create();

        return $customer->createToken('customer')->plainTextToken;
    }
}
