<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the 'performance_goals' table.
 * This covers "Goal Setting" and the "Objective" part of "OKR Tracking".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('performance_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            // Can be tied to a specific cycle, or be ongoing (nullable)
            $table->foreignId('review_cycle_id')->nullable()->constrained('performance_review_cycles')->onDelete('set null');
            
            $table->string('title'); // The "Objective"
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('due_date');
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'on_hold', 'cancelled'])->default('not_started');
            
            // Manager who set/approved this goal
            $table->foreignId('manager_id')->nullable()->constrained('employees')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_goals');
    }
};