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
       Schema::create('employee_policy_acknowledgements', function (Blueprint $table) {

    $table->id();

    $table->foreignId('organization_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('employee_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('policy_master_id')
        ->constrained()
        ->cascadeOnDelete();

    // Employee opened policy
    $table->boolean('is_viewed')->default(false);

    $table->timestamp('viewed_at')->nullable();

    // Employee acknowledged
    $table->boolean('is_acknowledged')->default(false);

    $table->timestamp('acknowledged_at')->nullable();

    // Optional
    $table->string('ip_address')->nullable();

    $table->text('user_agent')->nullable();

    $table->timestamps();

$table->unique(
    [
        'organization_id',
        'employee_id',
        'policy_master_id'
    ],
    'org_emp_policy_uq'
);

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_policy_acknowledgements');
    }
};
