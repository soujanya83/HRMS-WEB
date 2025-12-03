<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the 'goal_key_results' table.
 * This covers "KPI Tracking" or the "Key Results" part of "OKR Tracking".
 * Each "Key Result" measures its parent "Goal".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('goal_key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_goal_id')->constrained('performance_goals')->onDelete('cascade');
            
            $table->string('description'); // e.g., "Reduce ticket response time", "Close 5 enterprise deals"
            
            $table->enum('type', ['number', 'percentage', 'currency', 'boolean'])->default('number');
            $table->decimal('start_value', 15, 2)->default(0);
            $table->decimal('target_value', 15, 2);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->text('notes')->nullable(); // For updates

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_key_results');
    }
};