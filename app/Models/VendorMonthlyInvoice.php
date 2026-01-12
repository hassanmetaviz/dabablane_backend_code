<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorMonthlyInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'month',
        'year',
        'total_commission_excl_vat',
        'total_vat',
        'total_commission_incl_vat',
        'invoice_number',
        'pdf_path',
        'generated_at',
    ];

    protected $casts = [
        'month' => 'date',
        'year' => 'integer',
        'total_commission_excl_vat' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_commission_incl_vat' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    /**
     * Get the vendor that owns the invoice.
     */
    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Generate invoice number.
     */
    public static function generateInvoiceNumber(int $vendorId, int $year, int $month): string
    {
        $vendorCode = str_pad($vendorId, 4, '0', STR_PAD_LEFT);
        $monthCode = str_pad($month, 2, '0', STR_PAD_LEFT);
        return "INV-{$year}{$monthCode}-{$vendorCode}-" . strtoupper(substr(md5(time() . $vendorId), 0, 6));
    }

    /**
     * Generate PDF for the invoice.
     */
    public function generatePDF(): string
    {

        return $this->pdf_path ?? '';
    }
}


