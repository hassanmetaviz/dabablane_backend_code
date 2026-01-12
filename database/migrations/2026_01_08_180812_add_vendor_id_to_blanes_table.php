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
        Schema::table('blanes', function (Blueprint $table) {
            // Add vendor_id column
            $table->foreignId('vendor_id')->nullable()->after('id')->constrained('users')->onDelete('cascade');
            $table->index('vendor_id');
        });

        // Data migration: Populate vendor_id from commerce_name
        // This will match existing blanes to vendors based on commerce_name = company_name
        DB::statement("
            UPDATE blanes b
            INNER JOIN users u ON b.commerce_name = u.company_name
            INNER JOIN model_has_roles mhr ON u.id = mhr.model_id
            INNER JOIN roles r ON mhr.role_id = r.id
            SET b.vendor_id = u.id
            WHERE r.name = 'vendor'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blanes', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropIndex(['vendor_id']);
            $table->dropColumn('vendor_id');
        });
    }

};
