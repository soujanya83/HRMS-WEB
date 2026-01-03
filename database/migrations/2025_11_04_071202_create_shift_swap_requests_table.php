<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the 'shift_swap_requests' table.
 * This manages the flow for the "Shift Swapping Request" page.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();

            // The employee initiating the request and their original rostered shift
            $table->foreignId('requester_employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('requester_roster_id')->constrained('rosters')->onDelete('cascade'); // The shift they want to give away

            // The employee being asked to swap
            $table->foreignId('requested_employee_id')->constrained('employees')->onDelete('cascade');
            // The shift the requester wants in return.
            // This can be nullable if the requester just wants the day off, not a direct swap.
            $table->foreignId('requested_roster_id')->nullable()->constrained('rosters')->onDelete('cascade');

            $table->enum('status', ['pending', 'approved_by_employee', 'approved_by_manager', 'rejected', 'cancelled'])->default('pending');
            
            $table->text('requester_reason')->nullable();
            $table->text('rejection_reason')->nullable(); // If rejected by employee or manager

            // Track manager approval (final step)
            $table->foreignId('manager_approver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('manager_approved_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
    }
};
