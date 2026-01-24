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
    Schema::disableForeignKeyConstraints();

    Schema::table('leaves', function (Blueprint $table) {
        $table->dropColumn('employee_id');
        $table->foreignId('employee_id')
            ->after('id')
            ->constrained('employees')
            ->onDelete('cascade');
    });

    Schema::enableForeignKeyConstraints();
}
    // public function down(): void
    // {
    //     Schema::table('leaves', function (Blueprint $table) {
    //         $table->dropForeign(['employee_id']);
    //         $table->dropColumn('employee_id');
    //     });
    // }
};
