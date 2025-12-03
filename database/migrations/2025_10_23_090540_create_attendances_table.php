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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // Foreign key linking attendance to an employee
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');

            // Attendance date (unique per employee)
            $table->date('date');

            // Clock-in and clock-out times
            $table->time('check_in')->nullable()->comment('Time employee clocks in');
            $table->time('check_out')->nullable()->comment('Time employee clocks out');

            // Break tracking (for detailed work-hour analytics)
            $table->time('break_start')->nullable()->comment('Break start time');
            $table->time('break_end')->nullable()->comment('Break end time');

            // Attendance status based on company policy
            $table->enum('status', [
                'present',       // Normal working day attendance
                'absent',        // Employee did not report
                'late',          // Employee clocked in after allowed grace time
                'half_day',      // Employee worked partial shift
                'on_leave',      // Approved leave
                'holiday',       // Company-declared holiday
                'work_on_holiday' // Employee worked on an official holiday
            ])->default('absent');

            // Total working hours after deducting break time
            $table->decimal('total_work_hours', 5, 2)->nullable()->comment('Net working hours excluding breaks');

            // Remarks for special cases (like working on holidays or remote work)
            $table->text('notes')->nullable()->comment('Additional remarks or manager notes');

            $table->timestamps();

            // Ensures no duplicate attendance record for the same employee on the same date
            $table->unique(['employee_id', 'date'], 'unique_attendance_per_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
