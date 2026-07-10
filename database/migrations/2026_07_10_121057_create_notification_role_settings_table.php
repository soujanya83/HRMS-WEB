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
        Schema::create('notification_role_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('role_name'); // e.g., 'superadmin', 'hr'
            $table->timestamp('muted_until')->nullable(); // Agar ye time future ka hai, to notification off rahega
            $table->timestamps();

            // Ek organization me ek role ki ek hi setting ho sakti hai
            $table->unique(['organization_id', 'role_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_role_settings');
    }
};
