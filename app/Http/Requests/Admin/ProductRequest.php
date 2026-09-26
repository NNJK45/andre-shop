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

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du produit est obligatoire.',
            'name.unique' => 'Un produit portant ce nom existe déjà. Choisissez un autre nom ou modifiez le produit existant.',
            'sku.unique' => 'Cette référence SKU est déjà utilisée par un autre produit.',
            'category_id.exists' => 'La catégorie sélectionnée n’existe plus. Actualisez la page et choisissez-en une autre.',
            'price.required' => 'Le prix du produit est obligatoire.',
            'price.numeric' => 'Le prix doit être un nombre valide.',
            'price.min' => 'Le prix ne peut pas être négatif.',
        ];
    }
}
