<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        if (! Schema::hasColumn('events', 'contact_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedBigInteger('contact_id')->nullable()->after('account_id')->index();
            });
        }

        if (! Schema::hasColumn('events', 'value')) {
            Schema::table('events', function (Blueprint $table) {
                $table->decimal('value', 12, 2)->default(0)->after('contact_id');
            });
        }

        if (Schema::hasTable('proposals') && ! Schema::hasColumn('proposals', 'event_id')) {
            Schema::table('proposals', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id')->nullable()->after('id')->index();
            });
        }

        if (Schema::hasTable('contracts') && ! Schema::hasColumn('contracts', 'event_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id')->nullable()->after('proposal_id')->index();
            });
        }

        if (Schema::hasTable('opportunities')) {
            $this->copyOpportunitiesOntoEvents();
        }

        if (Schema::hasTable('proposals') && Schema::hasColumn('proposals', 'event_id')) {
            Schema::table('proposals', function (Blueprint $table) {
                $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
            });
        }

        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'event_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
            });
        }

        if (Schema::hasTable('proposals') && Schema::hasColumn('proposals', 'opportunity_id')) {
            Schema::table('proposals', function (Blueprint $table) {
                $table->dropForeign(['opportunity_id']);
                $table->dropColumn('opportunity_id');
            });
        }

        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'opportunity_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropForeign(['opportunity_id']);
                $table->dropColumn('opportunity_id');
            });
        }

        if (Schema::hasColumn('events', 'opportunity_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('opportunity_id');
            });
        }

        Schema::dropIfExists('opportunities');
    }

    public function down(): void
    {
        if (! Schema::hasTable('opportunities')) {
            Schema::create('opportunities', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                $table->string('stage')->default('prospecting');
                $table->decimal('value', 12, 2)->default(0);
                $table->date('event_start_date')->nullable();
                $table->date('event_end_date')->nullable();
                $table->string('venue_space')->nullable();
                $table->unsignedInteger('expected_attendance')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('events') && ! Schema::hasColumn('events', 'opportunity_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedBigInteger('opportunity_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('proposals') && ! Schema::hasColumn('proposals', 'opportunity_id')) {
            Schema::table('proposals', function (Blueprint $table) {
                $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            });
        }

        if (Schema::hasTable('contracts') && ! Schema::hasColumn('contracts', 'opportunity_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            });
        }
    }

    protected function copyOpportunitiesOntoEvents(): void
    {
        $opportunities = DB::table('opportunities')->whereNull('deleted_at')->get();

        foreach ($opportunities as $opportunity) {
            $linked = null;
            if (Schema::hasColumn('events', 'opportunity_id')) {
                $linked = DB::table('events')->where('opportunity_id', $opportunity->id)->whereNull('deleted_at')->first();
            }
            if (! $linked) {
                $linked = DB::table('events')->where('name', $opportunity->name)->whereNull('deleted_at')->first();
            }

            $mappedStatus = match ($opportunity->stage) {
                'booked' => 'confirmed',
                'lost', 'cancelled' => 'cancelled',
                default => 'opportunity',
            };

            $startsAt = $opportunity->event_start_date
                ? $opportunity->event_start_date.' 00:00:00'
                : now()->toDateTimeString();
            $endsAt = $opportunity->event_end_date
                ? $opportunity->event_end_date.' 23:59:59'
                : ($opportunity->event_start_date
                    ? $opportunity->event_start_date.' 23:59:59'
                    : now()->addHour()->toDateTimeString());

            if ($linked) {
                $keepStatus = in_array($linked->status, ['confirmed', 'completed', 'cancelled'], true);
                DB::table('events')->where('id', $linked->id)->update([
                    'contact_id' => $opportunity->contact_id ?? $linked->contact_id,
                    'value' => $opportunity->value ?? $linked->value,
                    'account_id' => $linked->account_id ?: $opportunity->account_id,
                    'venue_space' => $linked->venue_space ?: $opportunity->venue_space,
                    'expected_attendance' => $linked->expected_attendance ?: $opportunity->expected_attendance,
                    'status' => $keepStatus ? $linked->status : $mappedStatus,
                    'updated_at' => now(),
                ]);
                $eventId = $linked->id;
            } else {
                $eventId = DB::table('events')->insertGetId([
                    'name' => $opportunity->name,
                    'status' => $mappedStatus,
                    'account_id' => $opportunity->account_id,
                    'contact_id' => $opportunity->contact_id,
                    'value' => $opportunity->value ?? 0,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'venue_space' => $opportunity->venue_space,
                    'expected_attendance' => $opportunity->expected_attendance,
                    'notes' => $opportunity->notes,
                    'owner_user_id' => $opportunity->owner_user_id,
                    'created_by' => $opportunity->created_by,
                    'updated_by' => $opportunity->updated_by,
                    'created_at' => $opportunity->created_at ?? now(),
                    'updated_at' => $opportunity->updated_at ?? now(),
                ]);
            }

            if (Schema::hasTable('proposals') && Schema::hasColumn('proposals', 'event_id')) {
                DB::table('proposals')->where('opportunity_id', $opportunity->id)->update(['event_id' => $eventId]);
            }
            if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'event_id')) {
                DB::table('contracts')->where('opportunity_id', $opportunity->id)->update(['event_id' => $eventId]);
            }
        }
    }
};
