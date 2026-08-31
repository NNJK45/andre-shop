<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_address' => ['required', 'array'],
            'shipping_address.full_name' => ['required', 'string', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:30'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'shipping_address.region' => ['nullable', 'string', 'max:100'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_address.country_code' => ['required', 'string', 'size:2'],
            'billing_address' => ['nullable', 'array'],
            'billing_address.full_name' => ['required_with:billing_address', 'string', 'max:255'],
            'billing_address.phone' => ['required_with:billing_address', 'string', 'max:30'],
            'billing_address.line1' => ['required_with:billing_address', 'string', 'max:255'],
            'billing_address.line2' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['required_with:billing_address', 'string', 'max:100'],
            'billing_address.region' => ['nullable', 'string', 'max:100'],
            'billing_address.postal_code' => ['nullable', 'string', 'max:30'],
            'billing_address.country_code' => ['required_with:billing_address', 'string', 'size:2'],
        ];
    }
}
