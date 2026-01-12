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
           Schema::table('users', function (Blueprint $table) {
               $table->string('company_name')->nullable();
               $table->string('landline')->nullable();
               $table->string('businessCategory')->nullable();
               $table->string('subCategory')->nullable();
               $table->text('description')->nullable();
               $table->text('address')->nullable();
               $table->string('ice')->nullable();
               $table->string('rc')->nullable();
               $table->string('vat')->nullable();
               $table->string('logoUrl')->nullable();
               $table->string('coverPhotoUrl')->nullable();
               $table->string('rcCertificateUrl')->nullable();
           });
       }

       /**
        * Reverse the migrations.
        */
       public function down(): void
       {
           Schema::table('users', function (Blueprint $table) {
               $table->dropColumn([
                   'company_name',
                   'landline',
                   'businessCategory',
                   'subCategory',
                   'description',
                   'address',
                   'ice',
                   'rc',
                   'vat',
                   'logoUrl',
                   'coverPhotoUrl',
                   'rcCertificateUrl'
               ]);
           });
       }
   };