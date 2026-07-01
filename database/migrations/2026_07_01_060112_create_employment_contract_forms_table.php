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
        Schema::create('employment_contract_forms', function (Blueprint $table) {
            $table->id();
            
            // Relational IDs
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // General Info
            $table->date('contract_date')->nullable();
            $table->string('educator_name');
            $table->text('address')->nullable();
            $table->string('position')->nullable(); // e.g., Co-Educator

            // Disclosure Section
            $table->date('disclosure_date')->nullable();
            $table->string('disclosure_signature_path')->nullable();

            // Schedule / Key Terms
            $table->string('employment_type')->nullable(); // Full-time/Part-time/Casual/Fixed term
            $table->string('hours_per_week')->nullable();
            $table->string('commencement_date')->nullable(); // Using string to allow formats like "Wednesday, 1st October 2026"
            $table->string('award_classification')->nullable();
            $table->string('remuneration')->nullable();

            // Acceptance Section
            $table->string('acceptance_name')->nullable();
            $table->string('contract_signature_path')->nullable();
            $table->date('contract_signature_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_contract_forms');
    }
};
