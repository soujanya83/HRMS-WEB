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
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['approved_by']);
              $table->foreign('employee_id')
                  ->references('user_id')
                  ->on('employees')
                  ->onDelete('cascade');

            $table->foreign('approved_by')
                  ->references('user_id')
                  ->on('employees')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            //
        });
    }
};
