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
        Schema::create('pidtdc_forms', function (Blueprint $table) {
            $table->id();
            
            // Relational IDs
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // ==========================================
            // PAGE 1: Consent Form
            // ==========================================
            $table->string('appointee_name')->nullable();
            $table->string('appointee_signature_path')->nullable();
            $table->date('appointee_signature_date')->nullable();
            
            $table->string('nominated_supervisor_name')->nullable();
            $table->string('nominated_supervisor_signature_path')->nullable();
            $table->date('nominated_supervisor_signature_date')->nullable();

            // ==========================================
            // PAGE 2: Compliance History Statement
            // ==========================================
            $table->text('compliance_actions_details')->nullable(); // Q1
            
            $table->boolean('has_suspended_certificate')->default(false); // Q2a
            $table->text('suspended_certificate_details')->nullable();
            
            $table->boolean('has_prohibition_notice')->default(false); // Q2b
            $table->text('prohibition_notice_details')->nullable();
            
            $table->boolean('has_refused_licence')->default(false); // Q3
            $table->text('refused_licence_details')->nullable();
            
            $table->string('declarant_full_name')->nullable();
            $table->string('declarant_address')->nullable();
            $table->date('declarant_dob')->nullable();
            $table->string('declarant_signature_path')->nullable();
            $table->date('declarant_signature_date')->nullable();
            
            $table->string('witness_name')->nullable();
            $table->string('witness_signature_path')->nullable();

            // ==========================================
            // PAGE 3: Checklist for Responsible Persons
            // ==========================================
            $table->string('checklist_employee_name')->nullable();
            $table->json('checklist_data')->nullable(); // Stores the 23 checklist items and dates
            $table->text('checklist_comments')->nullable();
            
            $table->string('checklist_ns_signature_path')->nullable(); // NS = Nominated Supervisor
            $table->date('checklist_ns_signature_date')->nullable();
            
            $table->string('checklist_rp_signature_path')->nullable(); // RP = Responsible Person
            $table->date('checklist_rp_signature_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pidtdc_forms');
    }
};
