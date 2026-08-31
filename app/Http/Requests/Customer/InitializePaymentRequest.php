<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitializePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = config('payments.driver') === 'nokash' ? 'required' : 'sometimes';

        return [
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'payment_method' => [$required, Rule::in(['MTN_MOMO', 'ORANGE_MONEY'])],
            'user_phone' => [$required, 'string', 'regex:/^\+?237[0-9]{9}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('user_phone');
        $method = $this->input('payment_method');

        if (is_string($phone)) {
            $digits = preg_replace('/\D+/', '', $phone);
            if (is_string($digits) && strlen($digits) === 9) {
                $digits = '237'.$digits;
            }
            $this->merge(['user_phone' => $digits]);
        }

        if (is_string($method)) {
            $this->merge(['payment_method' => strtoupper(trim($method))]);
        }

        if ($this->hasHeader('Idempotency-Key')) {
            $this->merge([
                'idempotency_key' => $this->header('Idempotency-Key'),
            ]);
        }
    }
}
