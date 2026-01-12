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
        Schema::table('blanes', function (Blueprint $table) {
            $table->integer('availability_per_day')->nullable()->after('max_reservation_par_creneau');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blanes', function (Blueprint $table) {
            $table->dropColumn('availability_per_day');
        });
    }
};