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
           Schema::table('timesheets', function (Blueprint $table) {
            if (!Schema::hasColumn('timesheets', 'is_overtime')) {
                $table->boolean('is_overtime')->default(false)->after('task_id');
            }

            if (!Schema::hasColumn('timesheets', 'regular_hours')) {
                $table->decimal('regular_hours', 5, 2)->nullable()->after('is_overtime');
            }

            if (!Schema::hasColumn('timesheets', 'overtime_hours')) {
                $table->decimal('overtime_hours', 5, 2)->nullable()->after('regular_hours');
            }
        });
    }

  
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
          Schema::table('timesheets', function (Blueprint $table) {
            if (Schema::hasColumn('timesheets', 'is_overtime')) {
                $table->dropColumn('is_overtime');
            }
            if (Schema::hasColumn('timesheets', 'regular_hours')) {
                $table->dropColumn('regular_hours');
            }
            if (Schema::hasColumn('timesheets', 'overtime_hours')) {
                $table->dropColumn('overtime_hours');
            }
        });
    }
};
