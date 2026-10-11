<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('timezone')->default('UTC');
            $table->string('locale')->default('en');
            $table->string('currency')->default('USD');
            $table->string('company_name')->nullable();
            $table->json('branding')->nullable();
            $table->json('defaults')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
