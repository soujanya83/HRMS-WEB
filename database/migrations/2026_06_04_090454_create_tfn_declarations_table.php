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
        Schema::create('tfn_declarations', function (Blueprint $table) {
            $table->id();
            
            // Relational IDs
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // ==========================================
            // SECTION A: PAYEE
            // ==========================================
            $table->string('tfn_number', 9)->nullable(); 
            $table->enum('tfn_exemption_type', ['applied', 'under_18', 'pensioner'])->nullable();
            
            $table->string('title')->nullable(); // Mr, Mrs, Miss, Ms, etc.
            $table->string('surname');
            $table->string('first_name');
            $table->string('other_names')->nullable();
            $table->string('previous_name')->nullable();
            $table->date('dob')->nullable();
            
            // Address
            $table->string('payee_address')->nullable();
            $table->string('payee_suburb')->nullable();
            $table->string('payee_state')->nullable();
            $table->string('payee_postcode')->nullable();
            $table->string('payee_email')->nullable();
            
            // Tax & Employment Details
            $table->enum('employment_basis', ['full_time', 'part_time', 'labour_hire', 'superannuation', 'casual'])->nullable();
            $table->enum('residency_status', ['australian_resident', 'foreign_resident', 'working_holiday_maker'])->nullable();
            $table->boolean('claim_tax_free_threshold')->default(false);
            $table->boolean('has_help_debt')->default(false);
            
            // Payee Signature
            $table->string('payee_signature_path')->nullable();
            $table->date('payee_declaration_date')->nullable();

            // ==========================================
            // SECTION B: PAYER
            // ==========================================
            $table->string('payer_abn')->nullable();
            $table->string('payer_branch_number')->nullable();
            $table->boolean('payer_applied_for_abn')->default(false);
            
            $table->string('payer_legal_name')->nullable();
            $table->string('payer_address')->nullable();
            $table->string('payer_suburb')->nullable();
            $table->string('payer_state')->nullable();
            $table->string('payer_postcode')->nullable();
            
            $table->string('payer_email')->nullable();
            $table->string('payer_contact_person')->nullable();
            $table->string('payer_phone')->nullable();
            $table->boolean('no_longer_makes_payments')->default(false);
            
            // Payer Signature
            $table->string('payer_signature_path')->nullable();
            $table->date('payer_declaration_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tfn_declarations');
    }
};
