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
       Schema::create('xero_timesheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_xero_connection_id')->index();
            $table->unsignedBigInteger('xero_connection_id')->index();
            
            // Xero Timesheet Details
            $table->string('xero_timesheet_id')->unique()->index();
            $table->string('xero_employee_id')->index();
            
            // Timesheet Period
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status'); // DRAFT, PROCESSED, APPROVED
            
            // Hours Summary
            $table->decimal('total_hours', 10, 2)->default(0);
            $table->decimal('ordinary_hours', 10, 2)->default(0);
            $table->decimal('overtime_hours', 10, 2)->default(0);
            
            // Metadata
            $table->json('timesheet_lines')->nullable(); // Daily breakdown
            $table->json('xero_data')->nullable();
            
            // Sync Info
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_synced')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Keys
            $table->foreign('employee_xero_connection_id')
                  ->references('id')
                  ->on('employee_xero_connections')
                  ->onDelete('cascade');
                  
            $table->foreign('xero_connection_id')
                  ->references('id')
                  ->on('xero_connections')
                  ->onDelete('cascade');
            
            // Indexes
            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_timesheets');
    }
};
