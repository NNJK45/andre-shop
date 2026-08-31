<?php

namespace App\Application\Supplier;

use App\Domain\Supplier\Models\Supplier;

class SupplierService
{
    public function create(array $attributes): Supplier
    {
        $supplier = Supplier::query()->create($attributes);

        return $supplier->refresh();
    }

    public function update(Supplier $supplier, array $attributes): Supplier
    {
        $supplier->update($attributes);

        return $supplier->refresh();
    }
}
