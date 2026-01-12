<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'price_ht',
        'original_price_ht',
        'duration_days',
        'description',
        'is_recommended',
        'display_order',
        'is_active',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}