<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the status enum to include new values
        DB::statement("ALTER TABLE purchases MODIFY COLUMN status ENUM('pending', 'completed', 'expired', 'cancelled', 'manual', 'failed') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE purchases MODIFY COLUMN status ENUM('pending', 'completed', 'failed', 'manual') NOT NULL DEFAULT 'pending'");
    }
};