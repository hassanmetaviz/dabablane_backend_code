<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = ['name', 'is_active'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    public function blanes()
    {
        return $this->hasMany(Blane::class);
    }

    public function merchants()
    {
        return $this->hasMany(Merchant::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }
}