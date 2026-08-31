<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_routes_require_an_authenticated_admin(): void
    {
        $this->getJson('/api/admin/inventory')->assertUnauthorized();

        $customer = User::factory()->create();
        $token = $customer->createToken('customer')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/inventory')
            ->assertForbidden();
    }

    public function test_admin_can_initialize_inventory_for_a_product_once(): void
    {
        $product = $this->product();
        $token = $this->adminToken();

        $this->withToken($token)
            ->postJson('/api/admin/inventory', [
                'product_id' => $product->id,
                'on_hand' => 20,
                'reorder_level' => 5,
                'reason' => 'Opening balance',
                'reference' => 'OPEN-001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.stockable_type', 'product')
            ->assertJsonPath('data.sku', 'SKU-001')
            ->assertJsonPath('data.on_hand', 20)
            ->assertJsonPath('data.available', 20)
            ->assertJsonPath('data.is_low_stock', false);

        $this->assertDatabaseHas('stock_movements', [
            'type' => StockMovementType::Initial->value,
            'on_hand_delta' => 20,
            'reference' => 'OPEN-001',
        ]);

        $this->withToken($token)
            ->postJson('/api/admin/inventory', [
                'product_id' => $product->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stockable');
    }

    public function test_admin_can_initialize_inventory_for_a_variant(): void
    {
        $product = $this->product();
        $variant = $product->variants()->create([
            'name' => 'Blue',
            'sku' => 'SKU-001-BLU',
        ]);

        $this->withToken($this->adminToken())
            ->postJson('/api/admin/inventory', [
                'variant_id' => $variant->id,
                'on_hand' => 4,
            ])
            ->assertCreated()
            ->assertJsonPath('data.stockable_type', 'variant')
            ->assertJsonPath('data.sku', 'SKU-001-BLU');
    }

    public function test_stock_operations_update_balances_and_create_an_audit_trail(): void
    {
        $product = $this->product();
        $token = $this->adminToken();
        $itemId = $this->withToken($token)
            ->postJson('/api/admin/inventory', [
                'product_id' => $product->id,
                'on_hand' => 10,
            ])
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/admin/inventory/{$itemId}/receive", [
                'quantity' => 5,
                'reference' => 'PO-100',
            ])
            ->assertOk()
            ->assertJsonPath('data.on_hand', 15)
            ->assertJsonPath('data.available', 15);

        $this->withToken($token)
            ->postJson("/api/admin/inventory/{$itemId}/reserve", [
                'quantity' => 6,
                'reference' => 'CART-100',
            ])
            ->assertOk()
            ->assertJsonPath('data.reserved', 6)
            ->assertJsonPath('data.available', 9);

        $this->withToken($token)
            ->postJson("/api/admin/inventory/{$itemId}/release", [
                'quantity' => 2,
                'reference' => 'CART-100',
            ])
            ->assertOk()
            ->assertJsonPath('data.reserved', 4)
            ->assertJsonPath('data.available', 11);

        $this->withToken($token)
            ->postJson("/api/admin/inventory/{$itemId}/adjust", [
                'quantity' => -3,
                'reason' => 'Damaged units',
            ])
            ->assertOk()
            ->assertJsonPath('data.on_hand', 12)
            ->assertJsonPath('data.reserved', 4)
            ->assertJsonPath('data.available', 8);

        $this->assertDatabaseCount('stock_movements', 5);
        $this->assertDatabaseHas('stock_movements', [
            'type' => StockMovementType::Adjustment->value,
            'on_hand_delta' => -3,
            'reason' => 'Damaged units',
        ]);
    }

    public function test_inventory_rejects_operations_that_break_stock_invariants(): void
    {
        $product = $this->product();
        $token = $this->adminToken();
        $itemId = $this->withToken($token)
            ->postJson('/api/admin/inventory', [
                'product_id' => $product->id,
                'on_hand' => 5,
            ])
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/admin/inventory/{$itemId}/reserve", ['quantity' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->withToken($token)
            ->postJson("/api/admin/inventory/{$itemId}/reserve", ['quantity' => 4])
            ->assertOk();

        $this->withToken($token)
            ->postJson("/api/admin/inventory/{$itemId}/adjust", [
                'quantity' => -2,
                'reason' => 'Invalid shrinkage',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->withToken($token)
            ->postJson("/api/admin/inventory/{$itemId}/release", ['quantity' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $itemId,
            'on_hand' => 5,
            'reserved' => 4,
        ]);
    }

    public function test_low_stock_endpoint_uses_available_quantity(): void
    {
        $token = $this->adminToken();
        $lowProduct = $this->product('Low Product', 'LOW-001');
        $healthyProduct = $this->product('Healthy Product', 'HEALTHY-001');

        $lowId = $this->withToken($token)
            ->postJson('/api/admin/inventory', [
                'product_id' => $lowProduct->id,
                'on_hand' => 5,
                'reorder_level' => 3,
            ])
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/admin/inventory/{$lowId}/reserve", ['quantity' => 2])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/admin/inventory', [
                'product_id' => $healthyProduct->id,
                'on_hand' => 10,
                'reorder_level' => 3,
            ])
            ->assertCreated();

        $this->withToken($token)
            ->getJson('/api/admin/inventory/low-stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'LOW-001')
            ->assertJsonPath('data.0.available', 3)
            ->assertJsonPath('data.0.is_low_stock', true);
    }

    private function product(string $name = 'Test Product', string $sku = 'SKU-001'): Product
    {
        return Product::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'sku' => $sku,
            'price' => 100,
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
