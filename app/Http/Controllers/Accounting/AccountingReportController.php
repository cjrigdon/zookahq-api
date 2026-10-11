<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Accounting\ApBill;
use App\Models\Accounting\ArBudget;
use App\Models\Accounting\ArInvoice;
use App\Models\Accounting\ArPayment;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\GlJournalLine;
use App\Models\Accounting\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AccountingReportController extends Controller
{
    use AuthorizesPermissions;

    public function summary(): JsonResponse
    {
        $this->authorizePermission('accounting.reports.view');

        $arOpen = ArInvoice::whereIn('status', ['sent', 'partial'])->sum('balance_due');
        $apOpen = ApBill::whereIn('status', ['open', 'partial'])->sum('balance_due');
        $inventoryValue = InventoryItem::query()
            ->selectRaw('COALESCE(SUM(quantity_on_hand * unit_cost),0) as value')
            ->value('value');
        $arCollected = ArPayment::where('status', 'completed')
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('amount');

        return response()->json([
            'data' => [
                'ar_open_balance' => (float) $arOpen,
                'ap_open_balance' => (float) $apOpen,
                'inventory_value' => (float) $inventoryValue,
                'ar_collected_mtd' => (float) $arCollected,
                'invoice_count' => ArInvoice::count(),
                'bill_count' => ApBill::count(),
            ],
        ]);
    }

    public function arAging(): JsonResponse
    {
        $this->authorizePermission('accounting.reports.view');

        return response()->json([
            'data' => $this->agingBuckets(
                ArInvoice::query()
                    ->whereIn('status', ['sent', 'partial'])
                    ->where('balance_due', '>', 0)
                    ->get(['id', 'invoice_number', 'bill_to_name', 'due_date', 'balance_due', 'status']),
                'invoice_number',
                'bill_to_name'
            ),
        ]);
    }

    public function apAging(): JsonResponse
    {
        $this->authorizePermission('accounting.reports.view');

        return response()->json([
            'data' => $this->agingBuckets(
                ApBill::query()
                    ->whereIn('status', ['open', 'partial'])
                    ->where('balance_due', '>', 0)
                    ->get(['id', 'bill_number', 'vendor_name', 'due_date', 'balance_due', 'status']),
                'bill_number',
                'vendor_name'
            ),
        ]);
    }

    public function trialBalance(): JsonResponse
    {
        $this->authorizePermission('accounting.reports.view');

        $rows = GlAccount::query()
            ->orderBy('code')
            ->get()
            ->map(function (GlAccount $account) {
                $debits = (float) GlJournalLine::query()
                    ->where('gl_account_id', $account->id)
                    ->whereHas('entry', fn ($q) => $q->where('status', 'posted'))
                    ->sum('debit');
                $credits = (float) GlJournalLine::query()
                    ->where('gl_account_id', $account->id)
                    ->whereHas('entry', fn ($q) => $q->where('status', 'posted'))
                    ->sum('credit');

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'debit' => round($debits, 2),
                    'credit' => round($credits, 2),
                    'balance' => round($debits - $credits, 2),
                ];
            })
            ->filter(fn ($row) => $row['debit'] != 0 || $row['credit'] != 0)
            ->values();

        return response()->json([
            'data' => [
                'rows' => $rows,
                'total_debits' => round($rows->sum('debit'), 2),
                'total_credits' => round($rows->sum('credit'), 2),
            ],
        ]);
    }

    public function budgetVsActual(): JsonResponse
    {
        $this->authorizePermission('accounting.reports.view');

        $rows = ArBudget::with('glAccount')->latest()->get()->map(function (ArBudget $budget) {
            $budgetAmount = (float) $budget->budget_amount;
            $actual = (float) $budget->actual_amount;
            if ($budget->gl_account_id) {
                $posted = GlJournalLine::query()
                    ->where('gl_account_id', $budget->gl_account_id)
                    ->whereHas('entry', function ($q) use ($budget) {
                        $q->where('status', 'posted')
                            ->whereDate('entry_date', '>=', $budget->period_start)
                            ->whereDate('entry_date', '<=', $budget->period_end);
                    })
                    ->selectRaw('COALESCE(SUM(credit - debit),0) as net')
                    ->value('net');
                if ($posted !== null && abs((float) $posted) > 0) {
                    $actual = (float) $posted;
                }
            }

            return [
                'id' => $budget->id,
                'name' => $budget->name,
                'period_start' => $budget->period_start?->toDateString(),
                'period_end' => $budget->period_end?->toDateString(),
                'gl_account' => $budget->glAccount ? [
                    'code' => $budget->glAccount->code,
                    'name' => $budget->glAccount->name,
                ] : null,
                'budget_amount' => $budgetAmount,
                'actual_amount' => round($actual, 2),
                'variance' => round($budgetAmount - $actual, 2),
            ];
        });

        return response()->json(['data' => $rows]);
    }

    protected function agingBuckets($documents, string $numberKey, string $partyKey): array
    {
        $buckets = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            '90_plus' => 0.0,
        ];
        $items = [];
        $today = Carbon::today();

        foreach ($documents as $doc) {
            $due = Carbon::parse($doc->due_date);
            $daysPastDue = $due->diffInDays($today, false);
            $amount = (float) $doc->balance_due;

            if ($daysPastDue <= 0) {
                $bucket = 'current';
            } elseif ($daysPastDue <= 30) {
                $bucket = '1_30';
            } elseif ($daysPastDue <= 60) {
                $bucket = '31_60';
            } elseif ($daysPastDue <= 90) {
                $bucket = '61_90';
            } else {
                $bucket = '90_plus';
            }

            $buckets[$bucket] += $amount;
            $items[] = [
                'id' => $doc->id,
                'number' => $doc->{$numberKey},
                'party' => $doc->{$partyKey},
                'due_date' => $due->toDateString(),
                'days_past_due' => max(0, (int) $daysPastDue),
                'balance_due' => $amount,
                'bucket' => $bucket,
                'status' => $doc->status,
            ];
        }

        return [
            'as_of' => $today->toDateString(),
            'buckets' => array_map(fn ($v) => round($v, 2), $buckets),
            'total' => round(array_sum($buckets), 2),
            'items' => $items,
        ];
    }
}
