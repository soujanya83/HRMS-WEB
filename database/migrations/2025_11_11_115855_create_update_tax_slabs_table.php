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
        Schema::table('tax_slabs', function (Blueprint $table) {
            if (!Schema::hasColumn('tax_slabs', 'organization_id')) {
                $table->foreignId('organization_id')
                    ->after('id')
                    ->constrained('organizations')
                    ->onDelete('cascade');
            }
        });
    }


    /**
     * Reverse the migrations.
     */
     public function down(): void
    {
        Schema::table('tax_slabs', function (Blueprint $table) {
            if (Schema::hasColumn('tax_slabs', 'organization_id')) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            }
        });
    }
};
