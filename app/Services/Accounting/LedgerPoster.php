<?php

namespace App\Services\Accounting;

use App\Models\Accounting\ApPayment;
use App\Models\Accounting\ArPayment;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\GlJournalEntry;
use App\Models\Accounting\GlJournalLine;

class LedgerPoster
{
    public function postArPayment(ArPayment $payment): GlJournalEntry
    {
        $cash = $this->account('1000', 'Cash', 'asset');
        $ar = $this->account('1100', 'Accounts Receivable', 'asset');

        return $this->balancedEntry(
            entryDate: $payment->paid_at?->toDateString() ?? now()->toDateString(),
            memo: 'AR payment '.$payment->payment_number,
            source: 'ar_payment',
            sourceId: $payment->id,
            debitAccountId: $cash->id,
            creditAccountId: $ar->id,
            amount: (float) $payment->amount,
            debitMemo: 'Cash receipt',
            creditMemo: 'Reduce AR',
        );
    }

    public function postApPayment(ApPayment $payment): GlJournalEntry
    {
        $ap = $this->account('2000', 'Accounts Payable', 'liability');
        $cash = $this->account('1000', 'Cash', 'asset');

        return $this->balancedEntry(
            entryDate: $payment->paid_at?->toDateString() ?? now()->toDateString(),
            memo: 'AP payment '.$payment->payment_number,
            source: 'ap_payment',
            sourceId: $payment->id,
            debitAccountId: $ap->id,
            creditAccountId: $cash->id,
            amount: (float) $payment->amount,
            debitMemo: 'Reduce AP',
            creditMemo: 'Cash disbursement',
        );
    }

    protected function account(string $code, string $name, string $type): GlAccount
    {
        return GlAccount::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'type' => $type, 'is_active' => true]
        );
    }

    protected function balancedEntry(
        string $entryDate,
        string $memo,
        string $source,
        int $sourceId,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $debitMemo,
        string $creditMemo,
    ): GlJournalEntry {
        $entry = GlJournalEntry::create([
            'entry_number' => DocumentNumber::next('JE'),
            'entry_date' => $entryDate,
            'status' => 'posted',
            'memo' => $memo,
            'source' => $source,
            'source_id' => $sourceId,
            'created_by' => auth()->id(),
        ]);

        GlJournalLine::create([
            'gl_journal_entry_id' => $entry->id,
            'gl_account_id' => $debitAccountId,
            'debit' => $amount,
            'credit' => 0,
            'memo' => $debitMemo,
        ]);
        GlJournalLine::create([
            'gl_journal_entry_id' => $entry->id,
            'gl_account_id' => $creditAccountId,
            'debit' => 0,
            'credit' => $amount,
            'memo' => $creditMemo,
        ]);

        return $entry->load('lines.account');
    }
}
