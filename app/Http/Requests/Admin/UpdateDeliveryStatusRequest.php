<?php

namespace App\Http\Requests\Admin;

use App\Domain\Delivery\Enums\DeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(DeliveryStatus::class)],
            'failure_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
