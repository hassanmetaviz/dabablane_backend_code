<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CommissionSettings;

class CommissionSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CommissionSettings::updateOrCreate(
            ['id' => 1],
            [
                'partial_payment_commission_rate' => 3.5, // 50% of standard 7%
                'vat_rate' => 20.00,
                'daba_blane_account_iban' => null, // To be set by admin
                'transfer_processing_day' => 'wednesday',
            ]
        );
    }
}


