<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function categories(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function products(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with(['category', 'images'])
            ->when(
                $request->filled('category'),
                fn ($query) => $query->whereHas(
                    'category',
                    fn ($category) => $category->where('slug', $request->string('category')->value())
                        ->where('is_active', true),
                ),
            )
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(
                    fn ($product) => $product
                        ->where('name', 'like', '%'.$request->string('search')->value().'%')
                        ->orWhere('sku', 'like', '%'.$request->string('search')->value().'%'),
                ),
            )
            ->latest()
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return ProductResource::collection($products);
    }

    public function product(Product $product): ProductResource
    {
        abort_unless($product->is_active, 404);

        $product->load([
            'category',
            'variants' => fn ($query) => $query->where('is_active', true),
            'images',
        ]);

        return new ProductResource($product);
    }
}
