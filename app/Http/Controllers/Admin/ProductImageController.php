<?php

namespace App\Http\Controllers\Admin;

use App\Application\Catalog\CatalogService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductImageRequest;
use App\Http\Resources\ProductImageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(
        ProductImageRequest $request,
        Product $product,
        CatalogService $catalog,
    ): JsonResponse {
        $attributes = $request->validated();
        $previousPrimaryImage = null;

        if ($request->hasFile('image')) {
            $previousPrimaryImage = $product->images()->where('is_primary', true)->first();
            $attributes['path'] = $request->file('image')->store('products', 'public');
        }

        unset($attributes['image']);
        $attributes['is_primary'] = $attributes['is_primary'] ?? true;

        $image = $catalog->createImage($product, $attributes);

        if ($previousPrimaryImage && $previousPrimaryImage->isNot($image)) {
            Storage::disk('public')->delete($previousPrimaryImage->path);
            $previousPrimaryImage->delete();
        }

        return (new ProductImageResource($image))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        ProductImageRequest $request,
        Product $product,
        ProductImage $image,
        CatalogService $catalog,
    ): ProductImageResource {
        return new ProductImageResource($catalog->updateImage($image, $request->validated()));
    }

    public function destroy(Product $product, ProductImage $image): Response
    {
        $image->delete();

        return response()->noContent();
    }
}
