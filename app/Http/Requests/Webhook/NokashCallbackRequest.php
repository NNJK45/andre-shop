<?php

namespace App\Http\Requests\Webhook;

use Illuminate\Foundation\Http\FormRequest;

class NokashCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:1'],
            'phone' => ['nullable', 'string', 'max:30'],
            'orderId' => ['required', 'string', 'max:255'],
        ];
    }
}
