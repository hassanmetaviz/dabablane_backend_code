<?php

namespace App\Http\Requests\Api\Front\V1;

use Illuminate\Foundation\Http\FormRequest;

class InitiateSubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_id' => ['required', 'integer', 'exists:purchases,id'],
        ];
    }
}

