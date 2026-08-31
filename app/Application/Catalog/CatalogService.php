<?php

namespace App\Application\Catalog;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogService
{
    public function createCategory(array $attributes): Category
    {
        return Category::query()->create($this->withSlug($attributes));
    }

    public function updateCategory(Category $category, array $attributes): Category
    {
        $category->update($this->withSlug($attributes));

        return $category->refresh();
    }

    public function createProduct(array $attributes): Product
    {
        $attributes = $this->withSlug($attributes);

        if (blank($attributes['sku'] ?? null)) {
            $attributes['sku'] = $this->generatedSku($attributes['name']);
        }

        return Product::query()->create($attributes);
    }

    public function updateProduct(Product $product, array $attributes): Product
    {
        $product->update($this->withSlug($attributes));

        return $product->refresh();
    }

    public function createVariant(Product $product, array $attributes): ProductVariant
    {
        return $product->variants()->create($attributes);
    }

    public function updateVariant(ProductVariant $variant, array $attributes): ProductVariant
    {
        $variant->update($attributes);

        return $variant->refresh();
    }

    public function createImage(Product $product, array $attributes): ProductImage
    {
        return DB::transaction(function () use ($product, $attributes): ProductImage {
            if (($attributes['is_primary'] ?? false) === true) {
                $product->images()->update(['is_primary' => false]);
            }

            return $product->images()->create($attributes);
        });
    }

    public function updateImage(ProductImage $image, array $attributes): ProductImage
    {
        return DB::transaction(function () use ($image, $attributes): ProductImage {
            if (($attributes['is_primary'] ?? false) === true) {
                $image->product->images()->whereKeyNot($image->getKey())->update(['is_primary' => false]);
            }

            $image->update($attributes);

            return $image->refresh();
        });
    }

    private function generatedSku(string $name): string
    {
        $base = 'AND-'.Str::upper(Str::limit(Str::slug($name), 90, ''));
        $base = $base === 'AND-' ? 'AND-PRODUCT' : $base;

        $sku = $base;
        $counter = 2;

        while (Product::query()->where('sku', $sku)->exists()) {
            $suffix = '-'.$counter++;
            $sku = Str::substr($base, 0, 100 - Str::length($suffix)).$suffix;
        }

        return $sku;
    }
    private function withSlug(array $attributes): array
    {
        if (Arr::has($attributes, 'name')) {
            $attributes['slug'] = Str::slug($attributes['name']);
        }

        return $attributes;
    }
}
