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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blane_id')->constrained('blanes')->onDelete('cascade');
            $table->foreignId('customers_id')->constrained('customers')->onDelete('cascade');
            $table->datetime('date');
            $table->string('phone', 20); // Changed from integer to string with max length 20
            $table->string('time');
            $table->integer('number_persons')->default(1)->nullable();
            $table->text('comments')->nullable();
            $table->string('NUM_RES')->nullable()->unique();
            $table->decimal('total_price', 10, 2)->nullable();
            $table->decimal('partiel_price', 10, 2)->nullable();
            $table->integer('quantity')->nullable();
            $table->enum('payment_method',['cash','online','partiel'])->default('cash');
            $table->enum('status',['confirmed','pending','shipped','cancelled','paid','failed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
