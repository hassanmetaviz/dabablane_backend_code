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
        Schema::create('vendor_payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_payment_id')->constrained('vendor_payments')->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('action')->comment('marked_processed, reverted_to_pending, date_updated');
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->integer('affected_rows')->nullable()->comment('For mass updates');
            $table->text('admin_note')->nullable();
            $table->timestamp('created_at');

            $table->index('vendor_payment_id');
            $table->index('admin_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_logs');
    }
};


