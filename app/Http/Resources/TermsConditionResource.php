<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TermsConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_url' => asset('storage/' . $this->file_path),
            'file_size' => $this->file_size,
            'file_type' => $this->file_type,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'version' => $this->version,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'uploaded_at' => $this->created_at->format('M d, Y'),
        ];
    }
}