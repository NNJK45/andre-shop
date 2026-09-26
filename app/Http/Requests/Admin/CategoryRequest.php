<?php

namespace App\Http\Requests\Admin;

use App\Domain\Catalog\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255', Rule::unique(Category::class)->ignore($category)],
            'description' => ['nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'file', 'image', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la catégorie est obligatoire.',
            'name.unique' => 'Une catégorie portant ce nom existe déjà. Choisissez un autre nom ou modifiez la catégorie existante.',
            'image.image' => 'Le fichier choisi doit être une image valide.',
            'image.max' => 'L’image ne doit pas dépasser 5 Mo.',
        ];
    }
}
