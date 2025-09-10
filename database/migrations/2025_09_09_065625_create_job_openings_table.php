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
        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->foreignId('designation_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->string('location');
            $table->enum('employment_type', ['Full-time', 'Part-time', 'Contract', 'Internship']);
            $table->enum('status', ['Open', 'Closed', 'On Hold', 'Draft'])->default('Draft');
            $table->date('posting_date');
            $table->date('closing_date')->nullable();
            // Note: Add a foreignId('created_by')->constrained('users') when you have a users/employees module
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_openings');
    }
};
