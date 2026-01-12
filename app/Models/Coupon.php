<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'discount', 'validity', 'max_usage', 'description', 'minPurchase', 'categories_id', 'is_active'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'categories_id');
    }
}