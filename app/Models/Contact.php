<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'fullName',
        'email',
        'type',
        'phone',
        'subject',
        'message',        
        'status'
    ];
}
