<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Subscription Plans
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique(); // e.g., "3-month-pack-launch-promo"
            $table->decimal('price_ht', 10, 2); // Price excluding tax (MAD)
            $table->decimal('original_price_ht', 10, 2)->nullable(); // For promo crossed-out price
            $table->integer('duration_days'); // Duration in days (e.g., 30, 90, 180)
            $table->text('description')->nullable(); // Free text for plan details
            $table->boolean('is_recommended')->default(false); // For "Best Value" badge
            $table->integer('display_order')->default(0); // Order for display
            $table->boolean('is_active')->default(true); // Plan visibility
            $table->timestamps();
        });

        // Add-ons
        Schema::create('add_ons', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Add-on name
            $table->decimal('price_ht', 10, 2); // Unit price excluding tax (MAD)
            $table->string('tooltip')->nullable(); // Short description (1 sentence)
            $table->integer('max_quantity')->default(1); // Max purchasable quantity
            $table->boolean('is_active')->default(true); // Add-on visibility
            $table->timestamps();
        });

        // Promo Codes
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Unique code (e.g., SUMMER20)
            $table->decimal('discount_percentage', 5, 2); // e.g., 10.00 for 10%
            $table->date('valid_from')->nullable(); // Start date
            $table->date('valid_until')->nullable(); // End date
            $table->boolean('is_active')->default(true); // Active status
            $table->timestamps();
        });

        // Purchases (Vendor Subscriptions)
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Vendor
            $table->foreignId('plan_id')->constrained()->onDelete('restrict'); // Plan
            $table->foreignId('promo_code_id')->nullable()->constrained()->onDelete('set null'); // Optional promo code
            $table->decimal('plan_price_ht', 10, 2); // Snapshot of plan price
            $table->decimal('discount_amount', 10, 2)->default(0.00); // Discount from promo
            $table->decimal('subtotal_ht', 10, 2); // Plan + add-ons before discount
            $table->decimal('vat_amount', 10, 2); // 20% VAT
            $table->decimal('total_ttc', 10, 2); // Total including tax
            $table->date('start_date'); // Access start
            $table->date('end_date'); // Access end
            $table->enum('payment_method', ['online', 'manual']); // Payment type
            $table->enum('status', ['pending', 'completed', 'failed', 'manual'])->default('pending'); // Payment status
            $table->string('cmi_transaction_id')->nullable(); // CMI transaction reference
            $table->timestamps();
        });

        // Purchase Add-ons (Pivot table for add-ons in a purchase)
        Schema::create('purchase_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->onDelete('cascade');
            $table->foreignId('add_on_id')->constrained()->onDelete('restrict');
            $table->integer('quantity')->default(1); // Quantity purchased
            $table->decimal('unit_price_ht', 10, 2); // Snapshot of add-on price
            $table->decimal('total_price_ht', 10, 2); // Total for this add-on
            $table->timestamps();
        });

        // Invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique(); // e.g., DABA-INV-2025-0001
            $table->string('pdf_path'); // Path to stored PDF
            $table->date('issued_at');
            $table->timestamps();
        });

        // Configurations (Admin-configurable items)
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->string('billing_email')->nullable()->default('facturation@dabablane.com');
            $table->string('contact_email')->nullable()->default('contact@dabablane.com');
            $table->string('contact_phone')->nullable()->default('+212615170064');
            $table->string('invoice_logo_path')->nullable(); // e.g., uploads/logo.png
            $table->text('invoice_legal_mentions')->nullable(); // Legal text for invoices
            $table->string('invoice_prefix')->nullable()->default('DABA-INV-'); // Invoice number prefix
            $table->string('commission_pdf_path')->nullable(); // Path to commission PDF
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_add_ons');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('add_ons');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('configurations');
    }
};
