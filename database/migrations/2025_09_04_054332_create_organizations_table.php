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
        if (!Schema::hasTable('organizations')) {

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_number')->unique()->comment('ABN/ACN in Australia');
            $table->text('address')->nullable();
            $table->string('contact_email')->unique();
            $table->string('contact_phone')->nullable();
            $table->string('industry_type')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });

       }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
