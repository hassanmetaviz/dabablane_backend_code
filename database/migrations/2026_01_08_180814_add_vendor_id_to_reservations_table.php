<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('id')->constrained('users')->onDelete('cascade');
            $table->index('vendor_id');
        });

        // Data migration: Populate vendor_id from blane relationship
        DB::statement("
            UPDATE reservations r
            INNER JOIN blanes b ON r.blane_id = b.id
            SET r.vendor_id = b.vendor_id
            WHERE b.vendor_id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropIndex(['vendor_id']);
            $table->dropColumn('vendor_id');
        });
    }
};
