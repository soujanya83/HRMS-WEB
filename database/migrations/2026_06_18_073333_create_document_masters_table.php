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
        Schema::create('document_masters', function (Blueprint $table) {

            $table->id();

            $table->string('document_name');

            $table->string('document_type');

            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->string('icon')->nullable();

            $table->boolean('is_required')->default(true);

            $table->boolean('has_expiry')->default(false);

            $table->integer('expiry_years')->nullable();

            $table->integer('sort_order')->default(1);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_masters');
    }
};
