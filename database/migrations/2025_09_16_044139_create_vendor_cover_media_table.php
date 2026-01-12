<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendorCoverMediaTable extends Migration
{
    public function up()
    {
        Schema::create('vendor_cover_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('media_url');
            $table->string('media_type')->comment('image or video');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('vendor_cover_media');
    }
}