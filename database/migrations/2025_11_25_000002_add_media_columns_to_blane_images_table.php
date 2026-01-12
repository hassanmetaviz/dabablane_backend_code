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
        Schema::table('blane_images', function (Blueprint $table) {
            $table->string('media_type', 20)->default('image')->after('image_url');
            $table->boolean('is_cloudinary')->default(false)->after('media_type');
            $table->string('cloudinary_public_id')->nullable()->after('is_cloudinary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blane_images', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'is_cloudinary', 'cloudinary_public_id']);
        });
    }
};


