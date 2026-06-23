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
        Schema::create('policy_masters', function (Blueprint $table) {

            $table->id();

            $table->string('policy_name');

            $table->string('slug')->unique();

            $table->longText('description')->nullable();

            $table->string('document')->nullable();

            $table->integer('sort_order')->default(1);

            $table->boolean('is_required')->default(true);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_masters');
    }
};
