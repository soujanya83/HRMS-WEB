<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('xero_webhook_events', function (Blueprint $table) {
            if (!Schema::hasColumn('xero_webhook_events', 'event_id')) {
                $table->string('event_id')->nullable()->index();
            }
            if (!Schema::hasColumn('xero_webhook_events', 'event_category')) {
                $table->string('event_category')->nullable()->index();
            }
            if (!Schema::hasColumn('xero_webhook_events', 'resource_type')) {
                $table->string('resource_type')->nullable()->index();
            }
            if (!Schema::hasColumn('xero_webhook_events', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->index();
            }
            if (!Schema::hasColumn('xero_webhook_events', 'headers')) {
                $table->json('headers')->nullable();
            }
            if (!Schema::hasColumn('xero_webhook_events', 'status')) {
                $table->string('status')->default('pending')->index();
            }
            if (!Schema::hasColumn('xero_webhook_events', 'processing_error')) {
                $table->text('processing_error')->nullable();
            }
            if (!Schema::hasColumn('xero_webhook_events', 'retry_count')) {
                $table->integer('retry_count')->default(0);
            }
            if (!Schema::hasColumn('xero_webhook_events', 'event_date_utc')) {
                $table->timestamp('event_date_utc')->useCurrent();
            }
            if (!Schema::hasColumn('xero_webhook_events', 'signature')) {
                $table->string('signature')->nullable();
            }
            if (!Schema::hasColumn('xero_webhook_events', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('xero_webhook_events', function (Blueprint $table) {
            $cols = [
                'event_id',
                'event_category',
                'resource_type',
                'tenant_id',
                'headers',
                'status',
                'processing_error',
                'retry_count',
                'event_date_utc',
                'signature',
                'deleted_at'
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('xero_webhook_events', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
