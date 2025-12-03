<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();

            // Employee and organization linkage
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->onDelete('cascade');

            // Optional attendance reference
            $table->foreignId('attendance_id')
                ->nullable()
                ->constrained('attendances')
                ->onDelete('cascade');

            // Work and overtime details
            $table->date('work_date');
            $table->decimal('expected_overtime_hours', 5, 2)->nullable()
                ->comment('Requested overtime in hours');
            $table->decimal('actual_overtime_hours', 5, 2)->nullable()
                ->comment('Approved/recorded overtime in hours');

            // Request info
            $table->text('reason')->nullable();
            $table->enum('status', [
                'pending',
                'manager_approved',
                'hr_approved',
                'rejected',
                'cancelled'
            ])->default('pending');

            // Approvals
            $table->foreignId('approved_by_manager')
                ->nullable()
                ->constrained('employees')
                ->onDelete('set null');

            $table->timestamp('approved_at_manager')->nullable();

            $table->foreignId('approved_by_hr')
                ->nullable()
                ->constrained('employees')
                ->onDelete('set null');

            $table->timestamp('approved_at_hr')->nullable();

            $table->text('manager_remarks')->nullable();
            $table->text('hr_remarks')->nullable();

            // System fields
            $table->boolean('payroll_processed')->default(false)
                ->comment('True when overtime reflected in payroll');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // Unique constraint to avoid duplicate requests for same date
            $table->unique(['employee_id', 'work_date'], 'unique_employee_overtime_request');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
