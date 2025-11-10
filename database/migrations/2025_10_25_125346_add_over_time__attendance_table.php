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
        Schema::table('attendances', function (Blueprint $table) {
            // Add new column safely
            if (!Schema::hasColumn('attendances', 'is_overtime')) {
                $table->boolean('is_overtime')->default(false)->before('total_work_hours');
            }
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'is_overtime')) {
                $table->dropColumn('is_overtime');
            }
        });
    }
};

