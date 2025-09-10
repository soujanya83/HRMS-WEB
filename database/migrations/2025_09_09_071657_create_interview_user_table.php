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
        // This is a pivot table for the many-to-many relationship between interviews and users (interviewers).
        // Assumes you have a 'users' table for your employees.
        Schema::create('interview_user', function (Blueprint $table) {
            $table->primary(['interview_id', 'user_id']);
            $table->foreignId('interview_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_user');
    }
};
