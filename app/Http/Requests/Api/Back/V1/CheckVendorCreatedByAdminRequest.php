<?php

namespace App\Http\Requests\Api\Back\V1;

use Illuminate\Foundation\Http\FormRequest;

class CheckVendorCreatedByAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }
}

