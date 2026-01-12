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
            // SHA-256 hash is 64 characters long
            $table->string('cancel_token')->nullable()->after('status');
            $table->index('cancel_token');
        });

        Schema::table('reservations', function (Blueprint $table) {
            // SHA-256 hash is 64 characters long
            $table->string('cancel_token')->nullable()->after('status');
            $table->index('cancel_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['cancel_token']);
            $table->dropColumn('cancel_token');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['cancel_token']);
            $table->dropColumn('cancel_token');
        });
    }
};
