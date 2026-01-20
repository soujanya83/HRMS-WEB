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
        Schema::create('xero_payslips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('xero_connection_id')->index();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('xero_pay_run_id')->index();
            $table->unsignedBigInteger('employee_xero_connection_id')->index();
            
            // Xero Payslip Details
            $table->string('xero_payslip_id')->unique()->index();
            $table->string('xero_employee_id')->index();
            
            // Earnings
            $table->decimal('wages', 15, 2)->default(0);
            $table->decimal('allowances', 15, 2)->default(0);
            $table->decimal('overtime', 15, 2)->default(0);
            $table->decimal('bonuses', 15, 2)->default(0);
            $table->decimal('total_earnings', 15, 2)->default(0);
            
            // Deductions
            $table->decimal('tax_deducted', 15, 2)->default(0);
            $table->decimal('super_deducted', 15, 2)->default(0);
            $table->decimal('other_deductions', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            
            // Reimbursements
            $table->decimal('reimbursements', 15, 2)->default(0);
            
            // Net Pay
            $table->decimal('net_pay', 15, 2)->default(0);
            
            // Hours
            $table->decimal('hours_worked', 10, 2)->nullable();
            $table->decimal('overtime_hours', 10, 2)->nullable();
            
            // Metadata
            $table->json('earnings_lines')->nullable(); // Detailed earnings breakdown
            $table->json('deduction_lines')->nullable(); // Detailed deductions breakdown
            $table->json('leave_lines')->nullable(); // Leave accruals/usage
            $table->json('reimbursement_lines')->nullable();
            $table->json('super_lines')->nullable();
            $table->json('xero_data')->nullable(); // Full payslip object
            
            // Sync Info
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_synced')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Keys
            $table->foreign('xero_pay_run_id')
                  ->references('id')
                  ->on('xero_pay_runs')
                  ->onDelete('cascade');
                  
            $table->foreign('employee_xero_connection_id')
                  ->references('id')
                  ->on('employee_xero_connections')
                  ->onDelete('cascade');
            
            // Indexes
            $table->index(['xero_pay_run_id', 'xero_employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_payslips');
    }
};
