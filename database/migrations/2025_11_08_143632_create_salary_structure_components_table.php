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
        Schema::create('salary_structure_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained('salary_structures')->onDelete('cascade');
            $table->foreignId('component_type_id')->constrained('salary_component_types')->onDelete('cascade');
            $table->decimal('percentage', 5, 2)->nullable(); // e.g., 40% of base
            $table->decimal('amount', 12, 2)->nullable(); // fixed value
            $table->boolean('is_custom')->default(false);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_structure_components');
    }
};
