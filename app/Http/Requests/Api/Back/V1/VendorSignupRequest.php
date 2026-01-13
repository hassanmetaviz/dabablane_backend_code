<?php

namespace App\Http\Requests\Api\Back\V1;

use Illuminate\Foundation\Http\FormRequest;

class VendorSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'firebase_uid' => ['required', 'string', 'unique:users,firebase_uid'],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'district' => ['nullable', 'string', 'max:100'],
            'subdistrict' => ['nullable', 'string', 'max:100'],
        ];
    }
}

