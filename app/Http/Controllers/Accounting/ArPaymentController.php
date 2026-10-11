<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Accounting\ArInvoice;
use App\Models\Accounting\ArPayment;
use App\Services\Accounting\DocumentNumber;
use App\Services\Accounting\LedgerPoster;
use App\Services\Payments\AuthorizeNetGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArPaymentController extends Controller
{
    use AuthorizesPermissions;

    public function index(): JsonResponse
    {
        $this->authorizePermission('accounting.ar_payments.view');

        return response()->json(['data' => ArPayment::with('invoice')->latest()->paginate(25)]);
    }

    public function store(Request $request, AuthorizeNetGateway $gateway, LedgerPoster $ledger): JsonResponse
    {
        $this->authorizePermission('accounting.ar_payments.manage');

        $data = $request->validate([
            'ar_invoice_id' => ['required', 'exists:ar_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'charge_card' => ['nullable', 'boolean'],
            'card' => ['nullable', 'array'],
            'card.number' => ['required_with:charge_card', 'string'],
            'card.expiration' => ['required_with:charge_card', 'string'],
            'card.cvv' => ['nullable', 'string'],
        ]);

        $invoice = ArInvoice::findOrFail($data['ar_invoice_id']);
        $authResponse = null;
        $transId = null;
        $method = $data['method'] ?? 'check';

        if (! empty($data['charge_card'])) {
            $charge = $gateway->charge([
                'amount' => $data['amount'],
                'invoice_number' => $invoice->invoice_number,
                'description' => 'AR payment '.$invoice->invoice_number,
                'card' => $data['card'] ?? [],
            ]);
            if (! $charge['success']) {
                return response()->json(['message' => $charge['message'], 'raw' => $charge['raw']], 422);
            }
            $authResponse = $charge['raw'];
            $transId = $charge['transaction_id'];
            $method = 'card';
        }

        $payment = DB::transaction(function () use ($data, $invoice, $method, $authResponse, $transId, $ledger) {
            $payment = ArPayment::create([
                'ar_invoice_id' => $invoice->id,
                'payment_number' => DocumentNumber::next('ARPAY'),
                'status' => 'completed',
                'method' => $method,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'reference' => $data['reference'] ?? null,
                'authorize_net_trans_id' => $transId,
                'authorize_net_response' => $authResponse,
                'notes' => $data['notes'] ?? null,
                'via_portal' => false,
                'created_by' => auth()->id(),
            ]);

            $invoice->recalculateTotals();
            $ledger->postArPayment($payment);

            return $payment->load('invoice');
        });

        return response()->json(['data' => $payment], 201);
    }
}
