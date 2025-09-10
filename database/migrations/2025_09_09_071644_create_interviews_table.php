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
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->onDelete('cascade');
            $table->string('interview_type')->comment('e.g., Phone Screen, Technical, HR Round');
            $table->dateTime('scheduled_at');
            $table->string('location')->comment('Can be a physical address or a video call link');
            $table->enum('status', ['Scheduled', 'Completed', 'Cancelled', 'Rescheduled'])->default('Scheduled');
            $table->text('feedback')->nullable();
            $table->enum('result', ['Progress', 'Hold', 'Reject'])->nullable();
            // Note: Add a foreignId('scheduled_by')->constrained('users') when you have a users/employees module
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
