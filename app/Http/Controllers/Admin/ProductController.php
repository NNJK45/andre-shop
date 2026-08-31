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
        $product = $catalog->createProduct($request->validated())->load(['category', 'variants', 'images']);

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
        $product = $catalog->updateProduct($product, $request->validated());

        return new ProductResource($product->load(['category', 'variants', 'images']));
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }
}
