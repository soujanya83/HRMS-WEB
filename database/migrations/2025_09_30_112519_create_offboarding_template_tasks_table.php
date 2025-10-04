<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offboarding_template_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offboarding_template_id', 'off_temp_id_foreign')->constrained('offboarding_templates')->onDelete('cascade');
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->integer('due_before_days')->nullable()->comment('e.g., Due 2 days before last working day');
            $table->string('default_assigned_role')->nullable()->comment('e.g., HR, IT, Finance, Manager');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_template_tasks');
    }
};
