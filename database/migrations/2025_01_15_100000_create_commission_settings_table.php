<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('partial_payment_commission_rate', 5, 2)->default(3.5)->comment('Default 50% of standard rate');
            $table->decimal('vat_rate', 5, 2)->default(20.00);
            $table->string('daba_blane_account_iban')->nullable();
            $table->enum('transfer_processing_day', ['wednesday'])->default('wednesday');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_settings');
    }
};


