<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the 'performance_review_cycles' table.
 * This defines the timeframes for reviews (e.g., "Q1 2025", "2025 Annual Review").
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('performance_review_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->string('name'); // e.g., "2025 Annual Review", "Q3 2025 Performance"
            $table->date('start_date');
            $table->date('end_date'); // The end of the period being reviewed
            $table->date('deadline'); // When manager/employee submissions are due
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_review_cycles');
    }
};