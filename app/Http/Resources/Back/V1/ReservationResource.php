<?php

namespace App\Http\Resources\Back\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        
        // Include customer data if relationship is loaded
        if ($this->relationLoaded('customer')) {
            $data['customer'] = [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
                'city' => $this->customer->city,
            ];
        }
        
        return $data;
    }
}
