<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('xero_leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->index();
            $table->string('xero_leave_type_id')->unique(); // Xero ki ID
            $table->string('name'); // e.g. Annual Leave
            $table->string('type_of_units')->nullable(); // Hours/Days
            $table->boolean('is_paid_leave')->default(true);
            $table->boolean('show_on_payslip')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_leave_types');
    }
};
