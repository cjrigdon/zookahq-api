<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('dataset'); // leads, opportunities, events, work_orders, ar_invoices, ap_bills
            $table->json('columns');
            $table->json('filters')->nullable();
            $table->string('sort_column')->nullable();
            $table->string('sort_direction')->default('desc'); // asc, desc
            $table->string('chart_type')->nullable(); // none, bar, pie, line
            $table->string('group_by')->nullable();
            $table->text('ai_prompt')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
