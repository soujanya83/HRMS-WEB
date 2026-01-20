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
        Schema::create('xero_webhook_events', function (Blueprint $table) {
            $table->id();

            // Event Details
            $table->string('event_id')->unique()->index();
            $table->string('event_type'); // UPDATE, CREATE, DELETE
            $table->string('event_category'); // PAYROLL, INVOICE, etc.
            $table->string('resource_type'); // EMPLOYEE, PAYRUN, etc.
            $table->string('resource_id')->index();
            $table->string('tenant_id')->index();

            // Payload
            $table->json('payload');
            $table->json('headers')->nullable();

            // Processing Status
            $table->string('status')->default('pending'); // pending, processed, failed
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->integer('retry_count')->default(0);

            // Metadata
            $table->timestamp('event_date_utc')->useCurrent();
            $table->unsignedBigInteger('organization_id')->nullable()->index();

            $table->string('signature')->nullable(); // For verification

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('event_type');
            $table->index('event_date_utc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_webhook_events');
    }
};
