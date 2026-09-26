<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_only_lists_active_categories_and_products(): void
    {
        $activeCategory = Category::query()->create([
            'name' => 'Computers',
            'slug' => 'computers',
            'is_active' => true,
        ]);
        Category::query()->create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'is_active' => false,
        ]);

        $activeProduct = Product::query()->create([
            'category_id' => $activeCategory->id,
            'name' => 'Laptop Pro',
            'slug' => 'laptop-pro',
            'sku' => 'LAP-001',
            'price' => 1500,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $activeCategory->id,
            'name' => 'Hidden Laptop',
            'slug' => 'hidden-laptop',
            'sku' => 'LAP-002',
            'price' => 1200,
            'is_active' => false,
        ]);

        $this->getJson('/api/customer/catalog/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'computers')
            ->assertJsonPath('data.0.products_count', 1);

        $this->getJson('/api/customer/catalog/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeProduct->id);
    }

    public function test_public_catalog_supports_category_and_search_filters(): void
    {
        $computers = Category::query()->create(['name' => 'Computers', 'slug' => 'computers']);
        $phones = Category::query()->create(['name' => 'Phones', 'slug' => 'phones']);

        Product::query()->create([
            'category_id' => $computers->id,
            'name' => 'Laptop Pro',
            'slug' => 'laptop-pro',
            'sku' => 'LAP-001',
            'price' => 1500,
        ]);
        Product::query()->create([
            'category_id' => $phones->id,
            'name' => 'Smartphone',
            'slug' => 'smartphone',
            'sku' => 'PHO-001',
            'price' => 900,
        ]);

        $this->getJson('/api/customer/catalog/products?category=computers&search=laptop')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'LAP-001');
    }

    public function test_public_product_details_include_only_active_variants(): void
    {
        $product = Product::query()->create([
            'name' => 'T-Shirt',
            'slug' => 't-shirt',
            'sku' => 'TSH-001',
            'price' => 25,
        ]);
        $product->variants()->create([
            'name' => 'Blue / M',
            'sku' => 'TSH-BLU-M',
            'attributes' => ['color' => 'blue', 'size' => 'M'],
        ]);
        $product->variants()->create([
            'name' => 'Archived',
            'sku' => 'TSH-OLD',
            'is_active' => false,
        ]);
        $product->images()->create([
            'path' => 'catalog/t-shirt.jpg',
            'is_primary' => true,
        ]);

        $this->getJson('/api/customer/catalog/products/t-shirt')
            ->assertOk()
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonPath('data.variants.0.sku', 'TSH-BLU-M')
            ->assertJsonPath('data.images.0.is_primary', true);
    }

    public function test_inactive_product_is_not_publicly_accessible(): void
    {
        Product::query()->create([
            'name' => 'Draft',
            'slug' => 'draft',
            'sku' => 'DRAFT-1',
            'price' => 10,
            'is_active' => false,
        ]);

        $this->getJson('/api/customer/catalog/products/draft')->assertNotFound();
    }

    public function test_admin_routes_require_an_authenticated_admin(): void
    {
        $this->getJson('/api/admin/products')->assertUnauthorized();

        $customer = User::factory()->create();
        $token = $customer->createToken('customer')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/products')
            ->assertForbidden();
    }

    public function test_dashboard_counts_all_active_products_beyond_the_first_page(): void
    {
        foreach (range(1, 22) as $index) {
            Product::query()->create([
                'name' => "Produit actif {$index}",
                'slug' => "produit-actif-{$index}",
                'sku' => "ACTIF-{$index}",
                'price' => 1000,
                'is_active' => true,
            ]);
        }

        foreach (range(1, 3) as $index) {
            Product::query()->create([
                'name' => "Produit inactif {$index}",
                'slug' => "produit-inactif-{$index}",
                'sku' => "INACTIF-{$index}",
                'price' => 1000,
                'is_active' => false,
            ]);
        }

        $this->withToken($this->adminToken())
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.active_products', 22)
            ->assertJsonPath('data.orders', 0)
            ->assertJsonPath('data.low_stock_count', 0);
    }

    public function test_admin_can_create_category_product_variant_and_images(): void
    {
        $token = $this->adminToken();

        Storage::fake('public');

        $categoryId = $this->withToken($token)
            ->post('/api/admin/categories', [
                'name' => 'Home Appliances',
                'description' => 'Appliances for the home.',
                'image' => UploadedFile::fake()->image('category.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'home-appliances')
            ->json('data.id');

        $productResponse = $this->withToken($token)
            ->post('/api/admin/products', [
                'category_id' => $categoryId,
                'name' => 'Coffee Maker',
                'sku' => 'COF-001',
                'description' => 'Automatic coffee maker.',
                'price' => 249.99,
                'image' => UploadedFile::fake()->image('coffee.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'coffee-maker')
            ->assertJsonPath('data.price', '249.99');

        $slug = $productResponse->json('data.slug');

        $this->withToken($token)
            ->postJson("/api/admin/products/{$slug}/variants", [
                'name' => 'Black',
                'sku' => 'COF-001-BLK',
                'attributes' => ['color' => 'black'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.color', 'black');

        $firstImageId = $this->withToken($token)
            ->postJson("/api/admin/products/{$slug}/images", [
                'path' => 'catalog/coffee-1.jpg',
                'is_primary' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/admin/products/{$slug}/images", [
                'path' => 'catalog/coffee-2.jpg',
                'is_primary' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('product_images', [
            'id' => $firstImageId,
            'is_primary' => false,
        ]);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('product_images', 3);
    }

    public function test_admin_can_create_product_without_sku(): void
    {
        $token = $this->adminToken();

        Product::query()->create([
            'name' => 'Existing product',
            'slug' => 'existing-product',
            'sku' => 'AND-DELL-T540',
            'price' => 100,
        ]);

        Storage::fake('public');

        $this->withToken($token)->withHeader('Accept', 'application/json')
            ->post('/api/admin/products', [
                'name' => 'Dell T540',
                'price' => 230000,
                'image' => UploadedFile::fake()->image('dell.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'AND-DELL-T540-2');
    }

    public function test_duplicate_catalog_names_return_clear_french_messages(): void
    {
        $token = $this->adminToken();
        Category::query()->create(['name' => 'Cuisine', 'slug' => 'cuisine']);
        Product::query()->create([
            'name' => 'Plaque automatique',
            'slug' => 'plaque-automatique',
            'sku' => 'AND-PLAQUE-AUTOMATIQUE',
            'price' => 15000,
        ]);

        $this->withToken($token)
            ->postJson('/api/admin/categories', ['name' => 'Cuisine'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Une catégorie portant ce nom existe déjà. Choisissez un autre nom ou modifiez la catégorie existante.');

        $this->withToken($token)->withHeader('Accept', 'application/json')
            ->post('/api/admin/products', [
                'name' => 'Plaque automatique',
                'price' => 15000,
                'image' => UploadedFile::fake()->image('plaque.jpg'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Un produit portant ce nom existe déjà. Choisissez un autre nom ou modifiez le produit existant.');
    }

    public function test_product_and_category_images_are_required_on_creation(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)
            ->postJson('/api/admin/categories', ['name' => 'Sans photo'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image.0', 'La photo de la catégorie est obligatoire.');

        $this->withToken($token)
            ->postJson('/api/admin/products', ['name' => 'Sans photo', 'price' => 1000])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image.0', 'La photo du produit est obligatoire.');
    }

    public function test_admin_can_update_and_delete_catalog_entities(): void
    {
        $token = $this->adminToken();
        $category = Category::query()->create(['name' => 'Old Name', 'slug' => 'old-name']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Old Product',
            'slug' => 'old-product',
            'sku' => 'OLD-001',
            'price' => 10,
        ]);

        $this->withToken($token)
            ->patchJson('/api/admin/categories/old-name', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-name');

        $this->withToken($token)
            ->patchJson('/api/admin/products/old-product', [
                'name' => 'New Product',
                'price' => 12.50,
            ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-product')
            ->assertJsonPath('data.price', '12.50');

        $this->withToken($token)
            ->deleteJson('/api/admin/products/new-product')
            ->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_nested_catalog_resources_are_scoped_to_their_product(): void
    {
        $token = $this->adminToken();
        $first = Product::query()->create([
            'name' => 'First',
            'slug' => 'first',
            'sku' => 'FIRST',
            'price' => 10,
        ]);
        $second = Product::query()->create([
            'name' => 'Second',
            'slug' => 'second',
            'sku' => 'SECOND',
            'price' => 20,
        ]);
        $variant = $second->variants()->create([
            'name' => 'Second Variant',
            'sku' => 'SECOND-V',
        ]);

        $this->withToken($token)
            ->patchJson("/api/admin/products/{$first->slug}/variants/{$variant->id}", [
                'name' => 'Invalid update',
            ])
            ->assertNotFound();
    }

    public function test_admin_can_edit_a_category_and_replace_its_image(): void
    {
        Storage::fake('public');
        $token = $this->adminToken();
        $category = Category::query()->create([
            'name' => 'Initial category',
            'slug' => 'initial-category',
            'image_path' => 'categories/old.jpg',
        ]);
        Storage::disk('public')->put('categories/old.jpg', 'old-image');

        $response = $this->withToken($token)->post('/api/admin/categories/initial-category', [
            '_method' => 'PATCH',
            'name' => 'Updated category',
            'description' => 'Updated description',
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('updated.jpg'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated category')
            ->assertJsonPath('data.slug', 'updated-category');

        Storage::disk('public')->assertMissing('categories/old.jpg');
        Storage::disk('public')->assertExists($category->fresh()->image_path);
    }

    public function test_admin_product_image_upload_replaces_the_previous_primary_file(): void
    {
        Storage::fake('public');
        $token = $this->adminToken();
        $product = Product::query()->create([
            'name' => 'Image product',
            'slug' => 'image-product',
            'sku' => 'IMAGE-1',
            'price' => 100,
        ]);
        $oldImage = $product->images()->create([
            'path' => 'products/old.jpg',
            'is_primary' => true,
        ]);
        Storage::disk('public')->put('products/old.jpg', 'old-image');

        $response = $this->withToken($token)->post('/api/admin/products/image-product/images', [
            'image' => UploadedFile::fake()->image('updated.jpg'),
            'alt_text' => 'Updated product',
            'is_primary' => '1',
        ]);

        $response->assertCreated()->assertJsonPath('data.is_primary', true);
        Storage::disk('public')->assertMissing('products/old.jpg');
        Storage::disk('public')->assertExists($response->json('data.path'));
        $this->assertDatabaseMissing('product_images', ['id' => $oldImage->id]);
    }

    private function adminToken(): string
    {
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        return $admin->createToken('admin')->plainTextToken;
    }
}
