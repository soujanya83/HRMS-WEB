<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the 'notifications' table.
 * This will store notifications for all modules, including Rostering.
 * It's based on Laravel's default notification table structure.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Use UUIDs for notifications
            $table->string('type'); // The class name of the notification (e.g., App\Notifications\ShiftSwapRequested)
            
            // The recipient of the notification (a User)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Polymorphic relation to the object that caused the notification
            // (e.g., a ShiftSwapRequest model)
            $table->morphs('notifiable');
            
            $table->text('data'); // JSON blob to store message, links, etc.
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('user_id'); // Add index for quick lookup
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
