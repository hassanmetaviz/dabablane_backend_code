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
        Schema::table('blanes', function (Blueprint $table) {
            $table->enum('visibility', ['private', 'public', 'link'])->default('public')->after('is_digital');
            $table->uuid('share_token')->nullable()->unique()->after('visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blanes', function (Blueprint $table) {
            $table->dropColumn('visibility');
            $table->dropColumn('share_token');
        });
    }
};
