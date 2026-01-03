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
       Schema::create('xero_pay_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('xero_connection_id')->index();
            
            // Xero Pay Run Details
            $table->string('xero_pay_run_id')->unique()->index();
            $table->string('xero_payroll_calendar_id')->index();
            $table->string('calendar_name')->nullable();
            $table->string('pay_run_name')->nullable();
            $table->string('pay_run_number')->nullable();
            $table->string('currency')->nullable();
            $table->string('payment_method')->nullable();
            $table->unsignedBigInteger('organization_id')->index();
            
            // Pay Run Dates
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->date('payment_date');
            
            // Status
            $table->string('status'); // DRAFT, POSTED
            $table->string('pay_run_type')->nullable(); // SCHEDULED, UNSCHEDULED, EARLIER_YEAR_UPDATE
            
            // Totals
            $table->decimal('total_wages', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->decimal('total_super', 15, 2)->default(0);
            $table->decimal('total_reimbursement', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net_pay', 15, 2)->default(0);
            
            // Sync Info
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_synced')->default(false);
            
            // Metadata
            $table->json('xero_data')->nullable(); // Full pay run object
            $table->integer('employee_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Keys
            $table->foreign('xero_connection_id')
                  ->references('id')
                  ->on('xero_connections')
                  ->onDelete('cascade');
            
            // Indexes
            $table->index('status');
            $table->index('payment_date');
            $table->index(['period_start_date', 'period_end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_pay_runs');
    }
};
