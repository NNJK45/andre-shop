<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Quote\Enums\QuoteStatus;
use App\Domain\Quote\Models\QuoteRequest;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierQuoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_list_suppliers(): void
    {
        $token = $this->adminToken();

        $response = $this->withToken($token)->postJson('/api/admin/suppliers', [
            'name' => 'Global Import',
            'contact_name' => 'Awa Mbiya',
            'email' => 'contact@global-import.test',
            'phone' => '+237690000000',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Global Import')
            ->assertJsonPath('data.is_active', true);

        $supplier = Supplier::query()->firstOrFail();

        $this->withToken($token)
            ->patchJson("/api/admin/suppliers/{$supplier->id}", [
                'phone' => '+237677000000',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.phone', '+237677000000')
            ->assertJsonPath('data.is_active', false);

        $this->withToken($token)
            ->getJson('/api/admin/suppliers')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_create_and_progress_a_supplier_quote_request(): void
    {
        $token = $this->adminToken();
        $supplier = Supplier::query()->create([
            'name' => 'Tech Wholesale',
            'email' => 'sales@tech-wholesale.test',
        ]);
        $product = Product::query()->create([
            'name' => 'Laptop Pro',
            'slug' => 'laptop-pro',
            'sku' => 'LAP-001',
            'price' => 350000,
        ]);

        $response = $this->withToken($token)->postJson('/api/admin/quote-requests', [
            'supplier_id' => $supplier->id,
            'currency' => 'xaf',
            'notes' => 'Prix pour réapprovisionnement.',
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => 4,
                    'quoted_unit_price' => 300000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', QuoteStatus::Draft->value)
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonPath('data.subtotal', '1200000.00')
            ->assertJsonPath('data.items.0.total', '1200000.00');

        $reference = $response->json('data.reference');

        $this->withToken($token)
            ->patchJson("/api/admin/quote-requests/{$reference}/status", ['status' => QuoteStatus::Sent->value])
            ->assertOk()
            ->assertJsonPath('data.status', QuoteStatus::Sent->value);

        $this->withToken($token)
            ->patchJson("/api/admin/quote-requests/{$reference}/status", ['status' => QuoteStatus::Received->value])
            ->assertOk()
            ->assertJsonPath('data.status', QuoteStatus::Received->value)
            ->assertJsonPath('data.responded_at', fn ($value) => $value !== null);

        $this->withToken($token)
            ->patchJson("/api/admin/quote-requests/{$reference}/status", ['status' => QuoteStatus::Accepted->value])
            ->assertOk()
            ->assertJsonPath('data.status', QuoteStatus::Accepted->value);

        $this->assertDatabaseHas('quote_requests', [
            'reference' => $reference,
            'supplier_id' => $supplier->id,
            'status' => QuoteStatus::Accepted->value,
            'total' => 1200000,
        ]);
        $this->assertDatabaseHas('quote_request_items', [
            'product_id' => $product->id,
            'quantity' => 4,
            'total' => 1200000,
        ]);
    }

    public function test_quote_status_transitions_are_enforced(): void
    {
        $token = $this->adminToken();
        $supplier = Supplier::query()->create(['name' => 'Local Supplier']);
        $quote = QuoteRequest::query()->create([
            'supplier_id' => $supplier->id,
            'reference' => 'RFQ-TEST-001',
            'status' => QuoteStatus::Draft,
            'currency' => 'XAF',
            'requested_at' => now(),
        ]);

        $this->withToken($token)
            ->patchJson("/api/admin/quote-requests/{$quote->reference}/status", [
                'status' => QuoteStatus::Received->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('quote_requests', [
            'reference' => $quote->reference,
            'status' => QuoteStatus::Draft->value,
        ]);
    }

    public function test_customer_cannot_manage_suppliers_or_quotes(): void
    {
        $customer = User::factory()->create();
        $token = $customer->createToken('customer')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/suppliers')
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/admin/quote-requests', [])
            ->assertForbidden();
    }

    private function adminToken(): string
    {
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        return $admin->createToken('admin')->plainTextToken;
    }
}
