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
        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->id();
            // Note: This should be a foreignId to an 'employees' table once created.
            // Using applicant_id for now as the link to the hired person.
            $table->foreignId('applicant_id')->constrained()->onDelete('cascade');
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['Pending', 'Completed', 'Overdue'])->default('Pending');
            // Note: Add a foreignId('assigned_to')->constrained('users') for assigning tasks to specific employees
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_tasks');
    }
};
