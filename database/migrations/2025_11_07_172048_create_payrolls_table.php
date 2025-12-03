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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();

            // 🔗 Relations
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('salary_structure_id')->nullable()->constrained('salary_structures')->onDelete('set null');
            
            // 📅 Period info
            $table->string('pay_period', 7); // e.g., "2025-11" (YYYY-MM)
            $table->date('from_date');
            $table->date('to_date');

            // 💵 Calculated amounts
            $table->decimal('gross_earnings', 12, 2)->default(0);
            $table->decimal('gross_deductions', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);

            // 🕒 Attendance summary
            $table->integer('working_days')->default(0);
            $table->integer('present_days')->default(0);
            $table->integer('leave_days')->default(0);
            $table->integer('overtime_hours')->default(0);
            $table->decimal('overtime_amount', 10, 2)->default(0);

            // 🧾 Tax details
            $table->decimal('tax_deducted', 12, 2)->default(0);
            $table->decimal('pf_contribution', 12, 2)->default(0);
            $table->decimal('esi_contribution', 12, 2)->default(0);

            // 💳 Payment
            $table->enum('payment_status', ['pending', 'processed', 'paid'])->default('pending');
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->nullable(); // e.g., Bank Transfer, Cash
            $table->string('transaction_ref')->nullable();

            // 🧮 Meta
            $table->json('component_breakdown')->nullable(); // store detailed component data (e.g. Basic, HRA, Tax)
            $table->text('remarks')->nullable();

            $table->timestamps();

            // 🔐 Constraints
            $table->unique(['employee_id', 'pay_period']); // one payroll per employee per month
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
