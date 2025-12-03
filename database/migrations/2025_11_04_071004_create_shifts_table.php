<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the 'shifts' table.
 * This table stores the shift templates for an organization.
 * e.g., "Morning Shift (9-5)", "Night Shift (10-6)"
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->string('name'); // e.g., "Morning Shift", "Night Shift"
            $table->time('start_time');
            $table->time('end_time');
            $table->string('color_code')->nullable(); // For calendar visualization
            $table->text('notes')->nullable(); // Default duties or notes for this shift
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
