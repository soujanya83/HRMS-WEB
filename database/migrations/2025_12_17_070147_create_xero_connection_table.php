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
        Schema::create('xero_connections', function (Blueprint $table) {
            $table->id();
            $table->integer('organization_id')->unsigned()->index();
            $table->string('tenant_id')->unique()->index();
            $table->string('tenant_name');
            $table->string('tenant_type')->default('ORGANISATION'); // ORGANISATION, PRACTICE
            $table->string('xero_organization_id')->unique()->index();
            $table->string('xero_organization_name');
            $table->string('xero_client_id');
            $table->string('xero_client_secret');


            // OAuth Tokens
            $table->text('access_token');
            $table->text('refresh_token');
            $table->text('id_token')->nullable();
            $table->timestamp('token_expires_at');
            $table->timestamp('refresh_token_expires_at')->nullable();

            // Connection Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('connected_at')->useCurrent();

            $table->timestamp('disconnected_at')->nullable();

            // Metadata
            $table->string('country_code', 2)->nullable(); // AU, UK, US, NZ
            $table->string('organisation_type')->nullable();
            $table->json('scopes')->nullable(); // Store authorized scopes

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('is_active');
            $table->index('token_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_connections');
    }
};
