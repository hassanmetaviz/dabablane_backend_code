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
        Schema::create('vendor_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->decimal('commission_rate', 5, 2)->comment('e.g., 7.00 for 7%');
            $table->decimal('partial_commission_rate', 5, 2)->nullable()->comment('50% of standard rate by default');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique constraint: category + vendor combination
            $table->unique(['category_id', 'vendor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_commissions');
    }
};


