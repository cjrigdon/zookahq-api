<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Accounting\ArBudget;
use App\Models\Accounting\ApBill;
use App\Models\Accounting\ApBillLine;
use App\Models\Accounting\ApPayment;
use App\Models\Accounting\ArInvoice;
use App\Models\Accounting\ArPayment;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\GlJournalEntry;
use App\Models\Accounting\GlJournalLine;
use App\Models\Accounting\InventoryItem;
use App\Services\Accounting\DocumentNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GlJournalEntryController extends Controller
{
    use AuthorizesPermissions;

    public function index(): JsonResponse
    {
        $this->authorizePermission('accounting.gl.view');

        return response()->json(['data' => GlJournalEntry::with('lines.account')->latest()->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('accounting.gl.manage');
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'memo' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.gl_account_id' => ['required', 'exists:gl_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string'],
        ]);

        $debits = collect($data['lines'])->sum(fn ($l) => (float) ($l['debit'] ?? 0));
        $credits = collect($data['lines'])->sum(fn ($l) => (float) ($l['credit'] ?? 0));
        if (round($debits, 2) !== round($credits, 2)) {
            return response()->json(['message' => 'Journal entry must balance (debits = credits).'], 422);
        }

        $entry = DB::transaction(function () use ($data) {
            $entry = GlJournalEntry::create([
                'entry_number' => DocumentNumber::next('JE'),
                'entry_date' => $data['entry_date'],
                'status' => 'posted',
                'memo' => $data['memo'] ?? null,
                'source' => 'manual',
                'created_by' => auth()->id(),
            ]);
            foreach ($data['lines'] as $line) {
                GlJournalLine::create([
                    'gl_journal_entry_id' => $entry->id,
                    'gl_account_id' => $line['gl_account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $entry->load('lines.account');
        });

        return response()->json(['data' => $entry], 201);
    }
}
