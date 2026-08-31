<?php

namespace App\Http\Controllers\Admin;

use App\Application\Catalog\CatalogService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductVariantController extends Controller
{
    public function store(
        ProductVariantRequest $request,
        Product $product,
        CatalogService $catalog,
    ): JsonResponse {
        $variant = $catalog->createVariant($product, $request->validated());

        return (new ProductVariantResource($variant))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        ProductVariantRequest $request,
        Product $product,
        ProductVariant $variant,
        CatalogService $catalog,
    ): ProductVariantResource {
        return new ProductVariantResource($catalog->updateVariant($variant, $request->validated()));
    }

    public function destroy(Product $product, ProductVariant $variant): Response
    {
        $variant->delete();

        return response()->noContent();
    }
}
