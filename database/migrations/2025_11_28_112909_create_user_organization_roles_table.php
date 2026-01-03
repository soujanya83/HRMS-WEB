<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_organization_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('role_id')->index(); // will reference spatie roles table
            $table->timestamps();

            // optional uniqueness constraint so same role not inserted twice
            $table->unique(['user_id','organization_id','role_id'], 'u_user_org_role');

            // foreign keys (optional if you prefer not to set FK constraints)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            // $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_organization_roles');
    }
};
