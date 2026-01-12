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
        Schema::create('vendor_monthly_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->date('month')->comment('First day of month (e.g., 2024-01-01)');
            $table->integer('year');
            $table->decimal('total_commission_excl_vat', 10, 2);
            $table->decimal('total_vat', 10, 2);
            $table->decimal('total_commission_incl_vat', 10, 2);
            $table->string('invoice_number')->unique();
            $table->string('pdf_path')->nullable();
            $table->dateTime('generated_at');
            $table->timestamps();

            $table->index('vendor_id');
            $table->index(['vendor_id', 'month', 'year']);
            $table->index('invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_monthly_invoices');
    }
};


