<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'description')) {
                $table->text('description')->nullable()->after('expected_attendance');
            }
            if (! Schema::hasColumn('events', 'purpose')) {
                $table->string('purpose')->nullable();
            }
            if (! Schema::hasColumn('events', 'load_in_at')) {
                $table->dateTime('load_in_at')->nullable()->after('ends_at');
            }
            if (! Schema::hasColumn('events', 'load_out_at')) {
                $table->dateTime('load_out_at')->nullable()->after('load_in_at');
            }
            if (! Schema::hasColumn('events', 'alcohol_served')) {
                $table->boolean('alcohol_served')->default(false);
            }
            if (! Schema::hasColumn('events', 'client_venue_budget')) {
                $table->decimal('client_venue_budget', 12, 2)->nullable()->after('value');
            }
            if (! Schema::hasColumn('events', 'planner_name')) {
                $table->string('planner_name')->nullable();
                $table->string('planner_title')->nullable();
                $table->string('planner_organization')->nullable();
                $table->string('planner_email')->nullable();
                $table->string('planner_phone')->nullable();
            }
            if (! Schema::hasColumn('events', 'referral')) {
                $table->string('referral')->nullable();
            }
        });

        if (Schema::hasColumn('events', 'notes') && Schema::hasColumn('events', 'description')) {
            DB::table('events')->whereNotNull('notes')->update([
                'description' => DB::raw('notes'),
            ]);
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }

        if (! Schema::hasTable('event_dignitaries')) {
            Schema::create('event_dignitaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->string('name');
                $table->string('title')->nullable();
                $table->string('organization')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_dignitaries');

        if (! Schema::hasColumn('events', 'notes')) {
            Schema::table('events', function (Blueprint $table) {
                $table->text('notes')->nullable();
            });
        }

        if (Schema::hasColumn('events', 'description')) {
            DB::table('events')->whereNotNull('description')->update([
                'notes' => DB::raw('description'),
            ]);
        }

        Schema::table('events', function (Blueprint $table) {
            foreach ([
                'description',
                'purpose',
                'load_in_at',
                'load_out_at',
                'alcohol_served',
                'client_venue_budget',
                'planner_name',
                'planner_title',
                'planner_organization',
                'planner_email',
                'planner_phone',
                'referral',
            ] as $col) {
                if (Schema::hasColumn('events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
