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
       

          Schema::create('organization_leaves', function (Blueprint $table) {
            $table->id();
            $table->string('leave_type'); // e.g., casual, sick, earned, maternity, etc.
            $table->integer('granted_days')->default(0); // total leaves per year
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');


            $table->boolean('carry_forward')->default(false); // can unused leaves carry forward?
            $table->integer('max_carry_forward')->nullable(); // maximum carry-forward days allowed

            $table->boolean('paid')->default(true); // paid or unpaid leave
            $table->boolean('requires_approval')->default(true); // does this need admin/manager approval?
            $table->boolean('is_active')->default(true); // soft toggle to deactivate leave type
            $table->boolean('allow_half_day')->default(false); // if half-day leave is allowed

            $table->string('gender_applicable')->nullable(); 
            // e.g., 'male', 'female', or 'both' for maternity/paternity leave

            $table->text('description')->nullable(); // policy details or notes

            $table->unsignedBigInteger('created_by')->nullable(); // HR/Admin who created

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_leaves');
    }
};
