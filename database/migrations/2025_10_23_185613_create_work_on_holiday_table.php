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
         Schema::create('work_on_holidays', function (Blueprint $table) {
            $table->id();

            // Employee and organization linkage
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->onDelete('cascade');

            // Holiday reference
            $table->foreignId('holiday_id')
                ->constrained('organization_holidays')
                ->onDelete('cascade');

            // Work date (usually equals holiday date)
            $table->date('work_date');

            // Reason for request
            $table->text('reason')->nullable()
                ->comment('Employee justification for working on holiday');

            // Expected and actual overtime
            $table->decimal('expected_overtime_hours', 5, 2)->nullable()
                ->comment('Estimated overtime hours');
            $table->decimal('actual_overtime_hours', 5, 2)->nullable()
                ->comment('System calculated overtime after check-out');

            // Approval workflow
            $table->enum('status', [
                'pending',     // waiting for approval
                'manager_approved', // manager approved, waiting for HR
                'hr_approved', // final approval
                'rejected',    // rejected by either manager or HR
                'cancelled'    // cancelled by employee before approval
            ])->default('pending');

            // Approver details
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

            // HR / Manager remarks
            $table->text('manager_remarks')->nullable();
            $table->text('hr_remarks')->nullable();

            // Payroll tracking
            $table->boolean('payroll_processed')->default(false)
                ->comment('Indicates whether the overtime compensation has been processed in payroll');

            // Audit trail
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'work_date'], 'unique_work_on_holiday_request');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_on_holidays');
    }
};
