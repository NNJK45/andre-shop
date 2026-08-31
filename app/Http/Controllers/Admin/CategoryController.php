<?php

namespace App\Http\Controllers\Admin;

use App\Application\Catalog\CatalogService;
use App\Domain\Catalog\Models\Category;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->withCount('products')->orderBy('name')->paginate(),
        );
    }

    public function store(CategoryRequest $request, CatalogService $catalog): JsonResponse
    {
        $attributes = $request->validated();

        if ($request->hasFile('image')) {
            $attributes['image_path'] = $request->file('image')->store('categories', 'public');
        }

        unset($attributes['image']);
        $category = $catalog->createCategory($attributes);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->loadCount('products'));
    }

    public function update(CategoryRequest $request, Category $category, CatalogService $catalog): CategoryResource
    {
        $attributes = $request->validated();

        if ($request->hasFile('image')) {
            $attributes['image_path'] = $request->file('image')->store('categories', 'public');
        }

        unset($attributes['image']);

        return new CategoryResource($catalog->updateCategory($category, $attributes));
    }

    public function destroy(Category $category): Response
    {
        $category->delete();

        return response()->noContent();
    }
}
