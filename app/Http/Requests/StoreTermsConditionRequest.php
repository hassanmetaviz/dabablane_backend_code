<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTermsConditionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'type' => 'required|in:user,vendor',
            'title' => 'required|string|max:255',
            'pdf_file' => 'required|file|mimes:pdf|max:10240', // 10MB max
            'description' => 'nullable|string',
            'version' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages()
    {
        return [
            'pdf_file.required' => 'PDF file is required',
            'pdf_file.mimes' => 'Only PDF files are allowed',
            'pdf_file.max' => 'PDF file size should not exceed 10MB',
        ];
    }
}