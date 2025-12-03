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
        Schema::create('tax_slabs', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_income', 12, 2);
            $table->decimal('max_income', 12, 2)->nullable(); // null = open-ended
            $table->decimal('tax_rate', 5, 2); // e.g., 10%, 20%
            $table->decimal('surcharge', 5, 2)->default(0);
            $table->decimal('cess', 5, 2)->default(0);
            $table->string('financial_year', 9)->default('2025-2026');
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_slabs');
    }
};
