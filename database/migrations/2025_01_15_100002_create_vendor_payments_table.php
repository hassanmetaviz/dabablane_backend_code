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
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->onDelete('set null');
            $table->decimal('total_amount_ttc', 10, 2)->comment('Total paid online');
            $table->enum('payment_type', ['full', 'partial'])->comment('Full or Partial payment');
            $table->decimal('commission_rate_applied', 5, 2)->comment('Rate used for calculation');
            $table->decimal('commission_amount_excl_vat', 10, 2);
            $table->decimal('commission_vat', 10, 2)->comment('20% VAT on commission');
            $table->decimal('commission_amount_incl_vat', 10, 2);
            $table->decimal('net_amount_ttc', 10, 2)->comment('Vendor reimbursement amount');
            $table->enum('transfer_status', ['pending', 'processed'])->default('pending');
            $table->dateTime('transfer_date')->nullable();
            $table->string('debit_account')->default('DabaBlane corporate account – [IBAN]');
            $table->string('credit_account')->nullable()->comment('Vendor RIB from profile');
            $table->text('reason')->default('Reimbursement of payment via DabaBlane platform net of platform commission');
            $table->date('booking_date')->nullable()->comment('Date of booking/reservation');
            $table->date('payment_date')->comment('Date payment was received');
            $table->date('week_start')->comment('Monday of the week for grouping');
            $table->date('week_end')->comment('Sunday of the week');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')->comment('Admin who processed');
            $table->timestamps();

            // Indexes for better query performance
            $table->index('vendor_id');
            $table->index('transfer_status');
            $table->index('payment_date');
            $table->index(['week_start', 'week_end']);
            $table->index(['vendor_id', 'transfer_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
    }
};


