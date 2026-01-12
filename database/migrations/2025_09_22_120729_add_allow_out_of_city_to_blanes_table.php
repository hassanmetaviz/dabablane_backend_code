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
            $table->boolean('allow_out_of_city')->default(false)->after('livraison_out_city');
        });
    }

    public function down()
    {
        Schema::table('blanes', function (Blueprint $table) {
            $table->dropColumn('allow_out_of_city');
        });
    }
    // public function up(): void
    // {
    //     Schema::table('blanes', function (Blueprint $table) {
    //         //
    //     });
    // }

    // /**
    //  * Reverse the migrations.
    //  */
    // public function down(): void
    // {
    //     Schema::table('blanes', function (Blueprint $table) {
    //         //
    //     });
    // }
};
