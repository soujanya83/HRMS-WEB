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
         Schema::create('organization_holidays', function (Blueprint $table) {
            $table->id();

            // Organization to which this holiday belongs
            $table->foreignId('organization_id')
                  ->constrained('organizations')
                  ->onDelete('cascade')
                  ->comment('Linked organization');

            // Holiday name and description
            $table->string('holiday_name', 100)
                  ->comment('Name of the holiday, e.g., Independence Day');

            $table->date('holiday_date')
                  ->comment('Date of the holiday');

            $table->enum('holiday_type', ['national', 'regional', 'custom'])
                  ->default('custom')
                  ->comment('Defines the scope of the holiday');

            $table->boolean('is_recurring')
                  ->default(false)
                  ->comment('True if the holiday repeats annually (e.g., Christmas)');

            $table->text('description')
                  ->nullable()
                  ->comment('Optional notes or description');

            $table->boolean('is_active')
                  ->default(true)
                  ->comment('Status flag for enabling/disabling the holiday');

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('employees')
                  ->onDelete('set null')
                  ->comment('Employee who created the holiday entry');

            $table->timestamps();

            $table->unique(['organization_id', 'holiday_date'], 'unique_org_holiday_per_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_holidays');
    }
};
