<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'address', 'city'];

    public function merchantOffers()
    {
        return $this->hasMany(MerchantOffer::class);
    }
}
