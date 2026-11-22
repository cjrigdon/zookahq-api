<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('name');
            $table->string('background_color', 7);
            $table->string('text_color', 7);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('code');
            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        $defaults = [
            ['code' => 'CONFERENCE', 'name' => 'Conference', 'background_color' => '#1F4E79', 'text_color' => '#FFFFFF', 'sort_order' => 0],
            ['code' => 'WEDDING', 'name' => 'Wedding', 'background_color' => '#9D174D', 'text_color' => '#FFFFFF', 'sort_order' => 1],
            ['code' => 'CORPORATE', 'name' => 'Corporate', 'background_color' => '#0F766E', 'text_color' => '#FFFFFF', 'sort_order' => 2],
            ['code' => 'SOCIAL', 'name' => 'Social', 'background_color' => '#B45309', 'text_color' => '#FFFFFF', 'sort_order' => 3],
        ];

        foreach ($defaults as $row) {
            DB::table('event_types')->insert([
                ...$row,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('event_type_id')->nullable()->after('status')->constrained('event_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_type_id');
        });
        Schema::dropIfExists('event_types');
    }
};
