<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTermsConditionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'type' => 'sometimes|in:user,vendor',
            'title' => 'sometimes|string|max:255',
            'pdf_file' => 'sometimes|file|mimes:pdf|max:10240',
            'description' => 'nullable|string',
            'version' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages()
    {
        return [
            'pdf_file.mimes' => 'Only PDF files are allowed',
            'pdf_file.max' => 'PDF file size should not exceed 10MB',
        ];
    }
}