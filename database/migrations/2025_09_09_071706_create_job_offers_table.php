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
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_opening_id')->constrained()->onDelete('cascade');
            $table->date('offer_date');
            $table->date('expiry_date')->nullable();
            $table->decimal('salary_offered', 10, 2);
            $table->date('joining_date');
            $table->enum('status', ['Sent', 'Accepted', 'Rejected', 'Withdrawn'])->default('Sent');
            $table->string('offer_letter_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_offers');
    }
};
