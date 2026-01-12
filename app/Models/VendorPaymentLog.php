<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorPaymentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_payment_id',
        'admin_id',
        'action',
        'previous_status',
        'new_status',
        'affected_rows',
        'admin_note',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Get the vendor payment that owns the log.
     */
    public function vendorPayment()
    {
        return $this->belongsTo(VendorPayment::class);
    }

    /**
     * Get the admin who performed the action.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Create a log entry.
     */
    public static function createLog(array $data): self
    {
        return static::create(array_merge($data, [
            'created_at' => now(),
        ]));
    }
}


