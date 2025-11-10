<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            // First, drop the old foreign keys (if they exist)
            $table->dropForeign(['task_id']);
            $table->dropForeign(['project_id']);

            // Then, add the correct constraints
            $table->foreign('task_id')
                ->references('id')
                ->on('tasks')
                ->onDelete('set null');

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            // Rollback changes
            $table->dropForeign(['task_id']);
            $table->dropForeign(['project_id']);

            // Re-add old wrong constraints (if needed)
            $table->foreign('task_id')
                ->references('id')
                ->on('attendances')
                ->onDelete('set null');

            $table->foreign('project_id')
                ->references('id')
                ->on('attendances')
                ->onDelete('set null');
        });
    }
};
