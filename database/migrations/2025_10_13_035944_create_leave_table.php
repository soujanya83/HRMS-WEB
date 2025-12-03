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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('reason')->nullable();
            $table->string('status')->default('pending'); // e.g., pending, approved, rejected
            $table->string('leave_type'); // corrected name
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedBigInteger('approved_by')->nullable(); // nullable if not approved yet
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('employee_id')->references('user_id')->on('employees')->onDelete('cascade');
            $table->foreign('approved_by')->references('user_id')->on('employees')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
