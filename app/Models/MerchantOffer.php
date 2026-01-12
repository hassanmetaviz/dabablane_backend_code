<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantOffer extends Model
{
    protected $fillable = ['merchant_id', 'offer_details', 'validity'];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
