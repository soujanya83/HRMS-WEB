<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_exit_id')->constrained('employee_exits')->onDelete('cascade');
            $table->string('task_name');
            $table->date('due_date');
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['Pending', 'Completed'])->default('Pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_tasks');
    }
};
