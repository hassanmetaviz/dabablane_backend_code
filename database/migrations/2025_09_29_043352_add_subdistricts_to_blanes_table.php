<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('blanes', function (Blueprint $table) {
            $table->string('subdistricts', 255)->nullable()->after('district');
        });
    }

    public function down()
    {
        Schema::table('blanes', function (Blueprint $table) {
            $table->dropColumn('subdistricts');
        });
    }
};
