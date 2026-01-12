<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    use HasFactory;

    protected $fillable = [
        'billing_email',
        'contact_email',
        'contact_phone',
        'invoice_logo_path',
        'invoice_legal_mentions',
        'invoice_prefix',
        'commission_pdf_path',
    ];
}