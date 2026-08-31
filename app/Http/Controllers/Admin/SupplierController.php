<?php

namespace App\Http\Controllers\Admin;

use App\Application\Supplier\SupplierService;
use App\Domain\Supplier\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierRequest;
use App\Http\Resources\SupplierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SupplierController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SupplierResource::collection(Supplier::query()->latest()->paginate());
    }

    public function store(SupplierRequest $request, SupplierService $suppliers): JsonResponse
    {
        return (new SupplierResource($suppliers->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function update(SupplierRequest $request, Supplier $supplier, SupplierService $suppliers): SupplierResource
    {
        return new SupplierResource($suppliers->update($supplier, $request->validated()));
    }

    public function destroy(Supplier $supplier): Response
    {
        $supplier->delete();

        return response()->noContent();
    }
}
