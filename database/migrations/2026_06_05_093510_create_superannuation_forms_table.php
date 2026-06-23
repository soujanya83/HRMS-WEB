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
        Schema::create('superannuation_forms', function (Blueprint $table) {
            $table->id();
            
            // Relational IDs
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // SECTION A: Choice Selection
            // Values can be: 'existing_fund', 'default_fund', 'smsf'
            $table->enum('super_choice_type', ['existing_fund', 'default_fund', 'smsf']);

            // SECTION B: Existing Super Fund
            $table->string('b_super_fund_name')->nullable();
            $table->string('b_super_fund_abn')->nullable();
            $table->string('b_usi')->nullable(); // Unique Superannuation Identifier
            $table->string('b_member_account_number')->nullable();
            $table->string('b_account_name')->nullable();
            $table->boolean('b_letter_of_compliance_attached')->default(false);

            // SECTION C: Employer's Default Super Fund
            $table->string('c_business_name')->nullable();
            $table->string('c_business_abn')->nullable();
            $table->string('c_super_fund_name')->nullable();
            $table->string('c_super_fund_abn')->nullable();
            $table->string('c_usi')->nullable();
            $table->boolean('c_choose_default_fund_checkbox')->default(false);

            // SECTION D: Self-Managed Super Fund (SMSF)
            $table->string('d_smsf_name')->nullable();
            $table->string('d_smsf_abn')->nullable();
            $table->string('d_smsf_esa')->nullable(); // Electronic Service Address
            $table->string('d_account_name')->nullable();
            $table->string('d_bank_account_name')->nullable();
            $table->string('d_bsb_code')->nullable();
            $table->string('d_account_number')->nullable();
            $table->boolean('d_provided_evidence_ato')->default(false);

            // Consolidated Common Signature block across choices
            $table->string('signature_path')->nullable();
            $table->date('declaration_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('superannuation_forms');
    }
};
