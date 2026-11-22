<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('space_styles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('spaces')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('seating_capacity')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['space_id', 'sort_order']);
        });

        Schema::create('space_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('space_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('spaces')->cascadeOnDelete();
            $table->foreignId('space_attribute_id')->constrained('space_attributes')->cascadeOnDelete();
            $table->boolean('value')->default(false);
            $table->timestamps();
            $table->unique(['space_id', 'space_attribute_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('space_id')->nullable()->after('venue_space')->constrained('spaces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('space_id');
        });
        Schema::dropIfExists('space_attribute_values');
        Schema::dropIfExists('space_attributes');
        Schema::dropIfExists('space_styles');
        Schema::dropIfExists('spaces');
    }
};
