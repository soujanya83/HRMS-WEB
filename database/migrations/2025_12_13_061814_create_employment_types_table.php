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

        Schema::create('employment_types', function (Blueprint $table) {
    $table->id();
    $table->integer('organization_id')->unsigned()->index();
    $table->string('name');
        $table->integer('min_work_hours');
    $table->integer('max_work_hours');
    $table->boolean('overtime_allowed')->default(true);
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_types');
    }
};
