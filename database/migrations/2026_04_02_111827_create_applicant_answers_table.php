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
        Schema::create('applicant_answers', function (Blueprint $table) {
           $table->id();

        $table->unsignedBigInteger('question_id');
        $table->string('applicant_name');
        $table->string('applicant_email')->nullable();

        $table->text('answer');
        $table->integer('rating')->default(0); // out of 5

        $table->timestamps();

        $table->foreign('question_id')->references('id')->on('interview_questions')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicant_answers');
    }
};
