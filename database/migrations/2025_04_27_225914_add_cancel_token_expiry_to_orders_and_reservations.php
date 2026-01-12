<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('cancel_token_created_at')->nullable()->after('cancel_token');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('cancel_token_created_at')->nullable()->after('cancel_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cancel_token_created_at');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('cancel_token_created_at');
        });
    }
};
