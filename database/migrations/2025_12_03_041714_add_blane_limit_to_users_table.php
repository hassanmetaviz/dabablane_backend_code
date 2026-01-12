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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('blane_limit')->default(6)->after('isDiamond');
        });

        // Update existing users to have default value
        DB::table('users')->whereNull('blane_limit')->update(['blane_limit' => 6]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('blane_limit');
        });
    }
    // public function up(): void
    // {
    //     Schema::table('users', function (Blueprint $table) {
    //         //
    //     });
    // }

    // /**
    //  * Reverse the migrations.
    //  */
    // public function down(): void
    // {
    //     Schema::table('users', function (Blueprint $table) {
    //         //
    //     });
    // }
};
