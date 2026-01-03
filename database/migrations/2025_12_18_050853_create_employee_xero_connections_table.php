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
     
        Schema::create('employee_xero_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('xero_connection_id')->index();
            $table->integer('organization_id')->unsigned()->index();
            
            // Xero IDs
            $table->string('xero_employee_id')->unique()->index(); // Main Xero employee ID
            $table->string('xero_contact_id')->nullable()->index(); // If employee is also a contact
            
            // Sync Status
            $table->boolean('is_synced')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('pending'); // pending, synced, failed, needs_update
            $table->text('sync_error')->nullable();
            
            // Xero Employee Details (cached for quick access)
            $table->string('xero_status')->nullable(); // ACTIVE, TERMINATED
            $table->date('xero_start_date')->nullable();
            $table->date('xero_termination_date')->nullable();
            $table->string('xero_employee_number')->nullable();
            
            // Metadata
            $table->json('xero_data')->nullable(); // Store full Xero employee object
            $table->json('mapping_config')->nullable(); // Custom field mappings
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Keys
            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->onDelete('cascade');
                  
            $table->foreign('xero_connection_id')
                  ->references('id')
                  ->on('xero_connections')
                  ->onDelete('cascade');
            
            // Unique constraint: one employee per Xero connection
            $table->unique(['employee_id', 'xero_connection_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_xero_connections');
    }
};
