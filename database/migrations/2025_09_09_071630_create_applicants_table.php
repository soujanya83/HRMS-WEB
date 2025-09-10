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
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_opening_id')->constrained()->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('resume_url');
            $table->text('cover_letter')->nullable();
            $table->string('source')->nullable()->comment('e.g., LinkedIn, Indeed, Referral');
            $table->enum('status', ['Applied', 'Screening', 'Interviewing', 'Offered', 'Hired', 'Rejected', 'Withdrawn'])->default('Applied');
            $table->date('applied_date');
            $table->timestamps();

            $table->unique(['job_opening_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
