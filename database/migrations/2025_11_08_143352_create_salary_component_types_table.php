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
        Schema::create('salary_component_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., Basic, HRA, PF, Tax, Bonus
            $table->enum('category', ['earning', 'deduction', 'benefit']);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_component_types');
    }
};
