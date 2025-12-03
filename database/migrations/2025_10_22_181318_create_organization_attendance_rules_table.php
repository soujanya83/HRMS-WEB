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

    Schema::create('organization_attendance_rules', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('organization_id');
    $table->string('shift_name')->nullable();
    $table->time('check_in');
    $table->time('check_out');
    $table->time('shift_start')->nullable();
    $table->time('shift_end')->nullable();
    $table->time('break_start')->nullable();
    $table->time('break_end')->nullable();
    $table->integer('late_grace_minutes')->default(0);
    $table->integer('half_day_after_minutes')->nullable();
    $table->boolean('allow_overtime')->default(false);
    $table->decimal('overtime_rate', 5, 2)->nullable();
    $table->string('weekly_off_days')->nullable();
    $table->boolean('flexible_hours')->default(false);
    $table->integer('absent_after_minutes')->default(120);
    $table->boolean('is_remote_applicable')->default(false);
    $table->integer('rounding_minutes')->default(0);
    $table->boolean('cross_midnight')->default(false);
    $table->decimal('late_penalty_amount', 8, 2)->nullable();
    $table->decimal('absent_penalty_amount', 8, 2)->nullable();
    $table->string('relaxation')->nullable();
    $table->text('policy_notes')->nullable();
    $table->string('policy_version')->nullable();
    $table->unsignedBigInteger('created_by');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
    $table->foreign('created_by')->references('id')->on('employees')->onDelete('cascade');
});


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_attendance_rules');
    }
};
