<?php

namespace App\Http\Controllers\Admin;

use App\Application\Catalog\CatalogService;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::query()->with(['category', 'variants', 'images'])->latest()->paginate(),
        );
    }

    public function store(ProductRequest $request, CatalogService $catalog): JsonResponse
    {
        $attributes = $request->safe()->except('image');
        $imagePath = $request->file('image')->store('products', 'public');

        try {
            $product = DB::transaction(function () use ($attributes, $imagePath, $catalog): Product {
                $product = $catalog->createProduct($attributes);
                $catalog->createImage($product, [
                    'path' => $imagePath,
                    'alt_text' => $product->name,
                    'position' => 0,
                    'is_primary' => true,
                ]);

                return $product;
            })->load(['category', 'variants', 'images']);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($imagePath);
            throw $exception;
        }

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'variants', 'images']));
    }

    public function update(ProductRequest $request, Product $product, CatalogService $catalog): ProductResource
    {
        $product = $catalog->updateProduct($product, $request->safe()->except('image'));

        return new ProductResource($product->load(['category', 'variants', 'images']));
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }
}
