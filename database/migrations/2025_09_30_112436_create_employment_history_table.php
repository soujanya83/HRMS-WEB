<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->foreignId('designation_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('Null means it is the current position');
            $table->text('reason_for_change')->nullable()->comment('e.g., Promotion, Department Transfer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_history');
    }
};
