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
       Schema::create('bonuses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->onDelete('cascade');
    $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
    $table->enum('type', ['bonus', 'incentive', 'festival', 'other']);
    $table->decimal('amount', 12, 2);
    $table->string('reason')->nullable();
    $table->string('bonus_month')->nullable(); // 10-2025 etc.
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
     $table->foreignId('created_by')->nullable()->constrained('employees');
    $table->foreignId('approved_by')->nullable()->constrained('employees');
    $table->foreignId('paid_in_payroll_id')->nullable()->constrained('payrolls');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonuses');
    }
};
