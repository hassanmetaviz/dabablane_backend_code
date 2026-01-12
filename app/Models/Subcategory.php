<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subcategory extends Model
{
    protected $fillable = ['category_id', 'name', 'description', 'status'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function blanes()
    {
        return $this->hasMany(Blane::class, 'subcategories_id');
    }
}