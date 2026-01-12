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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('custom_commission_rate', 5, 2)->nullable()->after('status')->comment('Vendor-specific commission rate override');
            $table->string('rib_account')->nullable()->after('ribUrl')->comment('Bank account (RIB)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['custom_commission_rate', 'rib_account']);
        });
    }
};


