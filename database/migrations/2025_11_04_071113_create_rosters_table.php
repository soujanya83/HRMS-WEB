<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the 'rosters' table.
 * This is the main scheduling table that assigns an employee to a shift on a specific date.
 * This powers "Shift Scheduling" and "Weekly/Monthly Roster".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rosters', function (Blueprint $table) {
            $table->id();
            // We link organization_id for easier data scoping, though it's available via employee_id.
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('cascade');
            $table->date('roster_date');
            
            // Optional: Store actual times in case they were manually adjusted
            // from the default shift times for this one instance.
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->text('notes')->nullable(); // e.g., "Covering for Jane"
            
            // Tracks who published this roster entry (e.g., a manager's user_id)
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();

            // An employee can only have one roster entry (one shift) per day.
            $table->unique(['employee_id', 'roster_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rosters');
    }
};
