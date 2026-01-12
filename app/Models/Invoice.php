<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'invoice_number',
        'pdf_path',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'date',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
}