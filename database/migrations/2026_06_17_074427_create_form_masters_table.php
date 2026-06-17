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
        Schema::create('form_masters', function (Blueprint $table) {
            $table->id();

            $table->string('form_name');
            $table->string('table_name')->unique();
            $table->string('slug')->unique();

            $table->integer('sort_order')->default(0);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_masters');
    }
};
