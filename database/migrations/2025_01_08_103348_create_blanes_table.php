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
        Schema::create('blanes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcategories_id')->nullable()->default(null)->constrained('subcategories')->onDelete('cascade');
            $table->foreignId('categories_id')->nullable()->default(null)->constrained('categories')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->text('description')->nullable();

            $table->decimal('price_current', 8, 2)->nullable();
            $table->decimal('price_old', 8, 2)->nullable();

            $table->text('advantages')->nullable();
            $table->text('conditions')->nullable();

            $table->string('city')->nullable();
            $table->enum('status',['active','inactive','expired'])->nullable()->default('active');
            
            $table->enum ('type', ['reservation', 'order'])->nullable()->default('order');
            $table->enum('reservation_type',['pre-reservation','instante'])->nullable()->default('instante');

            $table->boolean("online")->nullable()->default(false);
            $table->boolean("partiel")->nullable()->default(false);
            $table->boolean("cash")->nullable()->default(false);
            $table->integer("partiel_field")->nullable()->default(0);
            $table->integer("tva")->nullable()->default(0);

            $table->boolean('on_top')->nullable()->default(false);
            $table->boolean('on_home')->nullable()->default(false);
            $table->integer('views')->nullable()->default(0);

            $table->datetime('start_day')->nullable();
            $table->datetime('end_day')->nullable();

            $table->integer('stock')->nullable();
            $table->integer('max_orders')->nullable()->default(1);

            $table->integer('livraison_in_city')->nullable();
            $table->integer('livraison_out_city')->nullable();

            $table->dateTime('start_date')->nullable();
            $table->dateTime('expiration_date')->nullable();

            $table->string('slug')->unique()->nullable();
            $table->string('advantage')->nullable();

            $table->json('jours_creneaux')->nullable();
            $table->json('dates')->nullable();
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->integer('intervale_reservation')->nullable();
            $table->integer('nombre_personnes')->nullable();
            $table->integer('personnes_prestation')->nullable()->default(1);
            $table->integer('nombre_max_reservation')->nullable();
            $table->integer('max_reservation_par_creneau')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blanes');
    }
};
