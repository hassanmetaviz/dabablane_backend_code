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
            $table->string('video_url', 2048)->nullable()->after('visibility');
            $table->string('video_public_id')->nullable()->after('video_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blanes', function (Blueprint $table) {
            $table->dropColumn(['video_url', 'video_public_id']);
        });
    }
};


