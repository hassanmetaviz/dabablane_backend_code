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
        Schema::table('orders', function (Blueprint $table) {
            // Add comments column if it doesn't exist
            if (!Schema::hasColumn('orders', 'comments')) {
                $table->text('comments')->nullable()->after('payment_method');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Remove the comments column if it exists
            if (Schema::hasColumn('orders', 'comments')) {
                $table->dropColumn('comments');
            }
        });
    }
};
