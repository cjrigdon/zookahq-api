<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Accounting\ApBill;
use App\Models\Accounting\ApPayment;
use App\Services\Accounting\DocumentNumber;
use App\Services\Accounting\LedgerPoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApPaymentController extends Controller
{
    use AuthorizesPermissions;

    public function index(): JsonResponse
    {
        $this->authorizePermission('accounting.ap_payments.view');

        return response()->json(['data' => ApPayment::with('bill')->latest()->paginate(25)]);
    }

    public function store(Request $request, LedgerPoster $ledger): JsonResponse
    {
        $this->authorizePermission('accounting.ap_payments.manage');
        $data = $request->validate([
            'ap_bill_id' => ['required', 'exists:ap_bills,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = DB::transaction(function () use ($data, $ledger) {
            $bill = ApBill::findOrFail($data['ap_bill_id']);
            $payment = ApPayment::create([
                'ap_bill_id' => $bill->id,
                'payment_number' => DocumentNumber::next('APPAY'),
                'status' => 'completed',
                'method' => $data['method'] ?? 'check',
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $bill->recalculateTotals();
            $ledger->postApPayment($payment);

            return $payment->load('bill');
        });

        return response()->json(['data' => $payment], 201);
    }
}
