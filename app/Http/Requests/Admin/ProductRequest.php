<?php

namespace App\Http\Requests\Admin;

use App\Domain\Catalog\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => [$required, 'string', 'max:255', Rule::unique(Product::class)->ignore($product)],
            'sku' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique(Product::class)->ignore($product)],
            'description' => ['nullable', 'string'],
            'price' => [$required, 'numeric', 'min:0', 'decimal:0,2'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}