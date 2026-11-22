<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['account_id', 'created_at']);
        });

        if (Schema::hasColumn('accounts', 'notes')) {
            $accounts = DB::table('accounts')
                ->whereNotNull('notes')
                ->where('notes', '!=', '')
                ->get(['id', 'notes', 'created_by', 'updated_by', 'created_at', 'updated_at']);

            foreach ($accounts as $account) {
                DB::table('account_notes')->insert([
                    'account_id' => $account->id,
                    'body' => $account->notes,
                    'contact_id' => null,
                    'event_id' => null,
                    'created_by' => $account->created_by,
                    'updated_by' => $account->updated_by,
                    'created_at' => $account->created_at ?? now(),
                    'updated_at' => $account->updated_at ?? now(),
                ]);
            }

            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('accounts', 'notes')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('country');
            });
        }

        if (Schema::hasTable('account_notes')) {
            $notes = DB::table('account_notes')
                ->orderBy('id')
                ->get(['account_id', 'body']);

            $byAccount = [];
            foreach ($notes as $note) {
                $byAccount[$note->account_id][] = $note->body;
            }

            foreach ($byAccount as $accountId => $bodies) {
                DB::table('accounts')->where('id', $accountId)->update([
                    'notes' => implode("\n\n", $bodies),
                ]);
            }

            Schema::dropIfExists('account_notes');
        }
    }
};
