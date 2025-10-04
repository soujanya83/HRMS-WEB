<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('Links to the user login account.');
            $table->foreignId('applicant_id')->nullable()->constrained()->onDelete('set null')->comment('Original applicant record.');
            
            // Current Job Details
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->foreignId('designation_id')->constrained()->onDelete('cascade');
            $table->foreignId('reporting_manager_id')->nullable()->constrained('employees')->onDelete('set null');

            // Personal Information
            $table->string('employee_code')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('personal_email')->unique();
            $table->date('date_of_birth');
            $table->string('gender');
            $table->string('phone_number');
            $table->text('address');

            // Employment Details
            $table->date('joining_date');
            $table->enum('employment_type', ['Full-time', 'Part-time', 'Contract', 'Internship']);
            $table->enum('status', ['Active', 'On Probation', 'On Leave', 'Terminated'])->default('On Probation');

            // Australian HR Specifics
            $table->string('tax_file_number')->nullable()->comment('Encrypted TFN');
            $table->string('superannuation_fund_name')->nullable();
            $table->string('superannuation_member_number')->nullable();
            $table->string('bank_bsb')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('visa_type')->nullable()->comment('For non-citizen employees');
            $table->date('visa_expiry_date')->nullable();

            // Emergency Contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
