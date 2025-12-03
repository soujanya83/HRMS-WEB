<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the 'performance_feedback' table.
 * This covers the "Feedback" page for continuous, 360-degree feedback.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('performance_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            
            // Who is GIVING the feedback
            $table->foreignId('giver_employee_id')->constrained('employees')->onDelete('cascade');
            // Who is RECEIVING the feedback
            $table->foreignId('receiver_employee_id')->constrained('employees')->onDelete('cascade');
            
            $table->text('feedback_content');
            $table->enum('type', ['positive', 'constructive', 'general'])->default('general');
            
            // Controls who can see this feedback
            $table->enum('visibility', ['private', 'manager_only', 'public'])->default('private');

            // Can be linked to a specific review or goal, but often is standalone (nullable)
            $table->foreignId('performance_review_id')->nullable()->constrained('performance_reviews')->onDelete('set null');
            $table->foreignId('performance_goal_id')->nullable()->constrained('performance_goals')->onDelete('set null');
            
            $table->timestamp('read_at')->nullable(); // When the receiver saw it
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_feedback');
    }
};