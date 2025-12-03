<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the 'performance_reviews' table.
 * This is the main record for the "Performance Reviews" and "Appraisals" pages.
 * It links an employee, a manager, and a review cycle.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_cycle_id')->constrained('performance_review_cycles')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade'); // Employee being reviewed
            $table->foreignId('manager_id')->constrained('employees')->onDelete('cascade'); // Manager conducting review

            // Employee self-assessment
            $table->text('employee_comments')->nullable();
            $table->integer('employee_rating')->nullable(); // Optional self-rating (e.g., 1-5)
            $table->timestamp('employee_submitted_at')->nullable();

            // Manager assessment
            $table->text('manager_comments')->nullable(); // Overall appraisal
            $table->text('manager_feedback_strengths')->nullable();
            $table->text('manager_feedback_areas_for_improvement')->nullable();
            $table->integer('manager_rating')->nullable(); // Final rating (e.g., 1-5)
            $table->timestamp('manager_submitted_at')->nullable();

            // Final status
            $table->enum('status', ['pending', 'employee_submitted', 'manager_submitted', 'acknowledged'])->default('pending');
            $table->timestamp('acknowledged_at')->nullable(); // When employee confirms they've seen it

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};