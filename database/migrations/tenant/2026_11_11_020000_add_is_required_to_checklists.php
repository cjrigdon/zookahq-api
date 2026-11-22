<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklists', function (Blueprint $table) {
            $table->boolean('is_required')->default(false)->after('is_active');
            $table->index(['applies_to', 'is_active', 'is_required'], 'checklists_applies_required_index');
        });
    }

    public function down(): void
    {
        Schema::table('checklists', function (Blueprint $table) {
            $table->dropIndex('checklists_applies_required_index');
            $table->dropColumn('is_required');
        });
    }
};
