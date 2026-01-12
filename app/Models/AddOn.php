<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddOn extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'price_ht',
        'tooltip',
        'max_quantity',
        'is_active',
    ];

    public function purchases()
    {
        return $this->belongsToMany(Purchase::class, 'purchase_add_ons')
            ->withPivot('quantity', 'unit_price_ht', 'total_price_ht')
            ->withTimestamps();
    }
}