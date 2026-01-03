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
           Schema::create('xero_pay_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            
            $table->unsignedBigInteger('xero_connection_id')->index();
            
            // Pay Item Details
            $table->string('xero_pay_item_id')->index();
            $table->string('item_type'); // earnings, deduction, leave, reimbursement
            $table->string('category')->nullable(); // e.g., ORDINARYTIMEEARNINGS, ALLOWANCE
            $table->string('name');
            $table->string('display_name')->nullable();
            
            // Settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system_item')->default(false);
            $table->string('rate_type')->nullable(); // RATEPERUNIT, FIXEDAMOUNT, etc.
            $table->decimal('default_rate', 15, 2)->nullable();
            
            // Accounting
            $table->string('expense_account_code')->nullable();
            $table->string('liability_account_code')->nullable();
            
            // Metadata
            $table->json('xero_data')->nullable();
            
            // Sync Info
            $table->timestamp('last_synced_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Keys
            $table->foreign('xero_connection_id')
                  ->references('id')
                  ->on('xero_connections')
                  ->onDelete('cascade');
            
            // Indexes
            $table->index('item_type');
            $table->index('is_active');
            $table->unique(['xero_connection_id', 'xero_pay_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_pay_items');
    }
};
