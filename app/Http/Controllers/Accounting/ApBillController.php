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

class ApBillController extends Controller
{
    use AuthorizesPermissions;

    public function index(): JsonResponse
    {
        $this->authorizePermission('accounting.ap_bills.view');

        return response()->json(['data' => ApBill::with('lines')->latest()->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('accounting.ap_bills.manage');
        $data = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255'],
            'vendor_email' => ['nullable', 'email'],
            'status' => ['nullable', 'string', 'max:50'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'tax_total' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'numeric'],
            'lines.*.unit_cost' => ['required', 'numeric'],
            'lines.*.gl_account_id' => ['nullable', 'exists:gl_accounts,id'],
        ]);

        $bill = DB::transaction(function () use ($data) {
            $bill = ApBill::create([
                'bill_number' => DocumentNumber::next('BILL'),
                'vendor_name' => $data['vendor_name'],
                'vendor_email' => $data['vendor_email'] ?? null,
                'status' => $data['status'] ?? 'open',
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'],
                'tax_total' => $data['tax_total'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            foreach ($data['lines'] as $line) {
                $qty = (float) $line['quantity'];
                $cost = (float) $line['unit_cost'];
                ApBillLine::create([
                    'ap_bill_id' => $bill->id,
                    'gl_account_id' => $line['gl_account_id'] ?? null,
                    'description' => $line['description'],
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'line_total' => $qty * $cost,
                ]);
            }
            $bill->recalculateTotals();

            return $bill->fresh('lines');
        });

        return response()->json(['data' => $bill], 201);
    }

    public function show(ApBill $apBill): JsonResponse
    {
        $this->authorizePermission('accounting.ap_bills.view');

        return response()->json(['data' => $apBill->load(['lines', 'payments'])]);
    }

    public function update(Request $request, ApBill $apBill): JsonResponse
    {
        $this->authorizePermission('accounting.ap_bills.manage');
        $data = $request->validate([
            'vendor_name' => ['sometimes', 'string', 'max:255'],
            'vendor_email' => ['nullable', 'email'],
            'status' => ['nullable', 'string', 'max:50'],
            'bill_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date'],
            'tax_total' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['updated_by'] = auth()->id();
        $apBill->update($data);
        $apBill->recalculateTotals();

        return response()->json(['data' => $apBill->fresh(['lines', 'payments'])]);
    }

    public function destroy(ApBill $apBill): JsonResponse
    {
        $this->authorizePermission('accounting.ap_bills.manage');
        $apBill->delete();

        return response()->json(['message' => 'Bill deleted']);
    }
}
