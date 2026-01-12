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
        Schema::table('users', function (Blueprint $table) {
            $table->string('firebase_uid')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('provider')->nullable();
            $table->string('accessToken')->nullable();
            $table->string('avatar')->nullable();
            // Make password nullable to support password-less auth (e.g., Firebase)
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['firebase_uid', 'phone', 'city', 'provider', 'accessToken', 'avatar']);
            // Revert password to non-nullable if needed
            $table->string('password')->change();
        });
    }
};
