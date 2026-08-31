<?php

namespace App\Http\Requests\Admin;

use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variant = $this->route('variant');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'sku' => [$required, 'string', 'max:100', Rule::unique(ProductVariant::class)->ignore($variant)],
            'price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
