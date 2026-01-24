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
          Schema::create('xero_leave_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_xero_connection_id')->index();
            $table->unsignedBigInteger('xero_connection_id')->index();
            $table->unsignedBigInteger('organization_id')->index();
            
            // Xero Leave Details
            $table->string('xero_leave_id')->unique()->index();
            $table->string('xero_employee_id')->index();
            $table->string('xero_leave_type_id')->index();
            
            // Leave Details
            $table->string('leave_type_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status'); // APPROVED, PENDING, DECLINED
            
            // Leave Units
            $table->decimal('units', 10, 2); // Days or hours
            $table->string('units_type')->default('Days'); // Days, Hours
            
            // Description
            $table->text('description')->nullable();
            $table->text('title')->nullable();
            
            // Metadata
            $table->json('leave_periods')->nullable(); // Detailed day-by-day breakdown
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
        Schema::dropIfExists('xero_leave_applications');
    }
};
