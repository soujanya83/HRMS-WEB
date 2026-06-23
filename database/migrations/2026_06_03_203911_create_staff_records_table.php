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
        Schema::create('staff_records', function (Blueprint $table) {
            $table->id();
            
            // Relational IDs
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Basic Details
            $table->string('name');
            $table->date('dob')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile_number')->nullable();
            $table->text('address')->nullable();

            // Qualifications & Training
            $table->text('relevant_qualifications')->nullable();
            $table->boolean('qualifications_copies_attached')->default(false);
            
            $table->text('other_approved_training')->nullable();
            $table->boolean('training_copies_attached')->default(false);

            // Checks & Certifications
            $table->string('wwc_wwvp_check_number')->nullable(); // Working with children/vulnerable people check
            $table->date('status_check_date')->nullable();
            $table->string('certified_supervisor_number')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_records');
    }
};
