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
        Schema::create('pay_periods', function (Blueprint $table) {
            $table->id();
            // Assuming you store organization_id as string or integer. 
            // Change to unsignedBigInteger if it relates to a local 'organizations' table.
            $table->string('organization_id')->index(); 
            
            $table->string('calendar_name');
            $table->string('calendar_type'); // WEEKLY, FORTNIGHTLY, etc.
            
            $table->date('start_date');
            $table->date('end_date');
            
            $table->integer('number_of_days');
            $table->boolean('is_current')->default(false);
            
            $table->timestamps();

            // Prevent duplicate entries for the same calendar and start date within an org
            $table->unique(['organization_id', 'calendar_name', 'start_date'], 'org_cal_start_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_periods');
    }
};
