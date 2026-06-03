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
        Schema::create('prohibition_notice_declarations', function (Blueprint $table) {
            $table->id();
            
            // Relational IDs
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Part A: Personal details
            $table->string('title')->nullable(); // [cite: 8]
            $table->string('last_name'); // [cite: 9]
            $table->string('first_name'); // [cite: 10]
            $table->string('mobile_number')->nullable(); // [cite: 11]
            $table->string('phone_number')->nullable(); // [cite: 12]
            $table->date('dob')->nullable(); // [cite: 13]
            $table->string('email')->nullable(); // [cite: 15]
            $table->string('address')->nullable(); // [cite: 15]
            $table->string('suburb')->nullable(); // [cite: 16]
            $table->string('state')->nullable(); // [cite: 17]
            $table->string('postcode')->nullable(); // [cite: 18]
            $table->text('former_names')->nullable(); // [cite: 19, 20]

            // Prohibition Questions (Yes/No mapped to boolean)
            $table->boolean('is_subject_to_prohibition')->default(false); // [cite: 21]
            $table->boolean('is_prohibited_other_law')->default(false); // [cite: 23]

            // Declaration Details (Optional fields if storing digital signature context)
            $table->string('declaration_place')->nullable(); // [cite: 33]
            $table->date('declaration_date')->nullable(); // [cite: 33]
            $table->string('witness_name')->nullable(); // [cite: 34]

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prohibition_notice_declarations');
    }
};
