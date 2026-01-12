<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'partial_payment_commission_rate',
        'vat_rate',
        'daba_blane_account_iban',
        'transfer_processing_day',
    ];

    protected $casts = [
        'partial_payment_commission_rate' => 'decimal:2',
        'vat_rate' => 'decimal:2',
    ];

    /**
     * Get or create the single commission settings instance (Singleton pattern)
     */
    public static function getSettings(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'partial_payment_commission_rate' => 3.5,
                'vat_rate' => 20.00,
                'transfer_processing_day' => 'wednesday',
            ]
        );
    }

    /**
     * Update settings
     */
    public static function updateSettings(array $data): self
    {
        $settings = static::getSettings();
        $settings->update($data);
        return $settings->fresh();
    }
}


