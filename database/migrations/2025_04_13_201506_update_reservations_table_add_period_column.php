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
        Schema::table('reservations', function (Blueprint $table) {
            $table->datetime('date')->nullable()->change();
            $table->string('time')->nullable()->change();
            $table->datetime('end_date')->nullable()->after('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->datetime('date')->nullable(false)->change();
            $table->string('time')->nullable(false)->change();
            $table->dropColumn('end_date');
        });
    }
};
