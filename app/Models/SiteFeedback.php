<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteFeedback extends Model
{
    protected $fillable = ['user_id', 'feedback'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
