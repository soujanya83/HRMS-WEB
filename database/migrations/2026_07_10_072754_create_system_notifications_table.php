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
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Core Relations
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Jis user ko notification dikhega
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete(); // Jisne action perform kiya (e.g., employee)
            
            // Notification Content
            $table->string('type'); // e.g., 'document_upload', 'leave_request', 'swap_request'
            $table->string('title');
            $table->text('message');
            
            // Flexible Data Payload (AI/HRMS ke liye bahut useful hai)
            $table->json('data')->nullable(); // Isme aap frontend ke liye link, document_id, route name bhej sakte hain
            
            // Tracking (Seen / Unseen)
            $table->timestamp('read_at')->nullable(); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
