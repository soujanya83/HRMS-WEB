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
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->onDelete('set null');
                    $table->foreignId('task_id')->nullable()->constrained('attendances')->onDelete('set null');
                            $table->foreignId('project_id')->nullable()->constrained('attendances')->onDelete('set null');
            $table->date('work_date');
            $table->string('project_name')->nullable();
            $table->string('task_description')->nullable();
            $table->decimal('hours_worked', 5, 2)->default(0);
            $table->string('status')->default('submitted'); // submitted, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('employees')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
