<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->date('event_date')->nullable()->after('notes');
            $table->timestamp('load_in_at')->nullable()->after('event_date');
            $table->timestamp('event_starts_at')->nullable()->after('load_in_at');
            $table->timestamp('event_ends_at')->nullable()->after('event_starts_at');
            $table->timestamp('load_out_at')->nullable()->after('event_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'event_date',
                'load_in_at',
                'event_starts_at',
                'event_ends_at',
                'load_out_at',
            ]);
        });
    }
};
