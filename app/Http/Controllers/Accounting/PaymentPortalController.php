<?php

namespace App\Http\Controllers\Accounting;

use App\Models\Accounting\ArInvoice;
use App\Models\Accounting\ArPayment;
use App\Services\Accounting\DocumentNumber;
use App\Services\Accounting\LedgerPoster;
use App\Services\Payments\AuthorizeNetGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentPortalController
{
    public function show(string $token): JsonResponse
    {
        $invoice = $this->resolveInvoice($token);

        return response()->json([
            'data' => [
                'invoice_number' => $invoice->invoice_number,
                'bill_to_name' => $invoice->bill_to_name,
                'bill_to_email' => $invoice->bill_to_email,
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'status' => $invoice->status,
                'subtotal' => $invoice->subtotal,
                'tax_total' => $invoice->tax_total,
                'total' => $invoice->total,
                'amount_paid' => $invoice->amount_paid,
                'balance_due' => $invoice->balance_due,
                'currency' => $invoice->currency,
                'notes' => $invoice->notes,
                'lines' => $invoice->lines,
                'payments' => $invoice->payments()->where('status', 'completed')->get(['payment_number', 'amount', 'paid_at', 'method']),
            ],
        ]);
    }

    public function pay(Request $request, string $token, AuthorizeNetGateway $gateway, LedgerPoster $ledger): JsonResponse
    {
        $invoice = $this->resolveInvoice($token);

        if ((float) $invoice->balance_due <= 0) {
            return response()->json(['message' => 'Invoice is already paid.'], 422);
        }

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'card.number' => ['required', 'string'],
            'card.expiration' => ['required', 'string'],
            'card.cvv' => ['nullable', 'string'],
            'payer_name' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) ($data['amount'] ?? $invoice->balance_due);
        if ($amount > (float) $invoice->balance_due + 0.001) {
            return response()->json(['message' => 'Amount exceeds balance due.'], 422);
        }

        $charge = $gateway->charge([
            'amount' => $amount,
            'invoice_number' => $invoice->invoice_number,
            'description' => 'Portal payment '.$invoice->invoice_number,
            'card' => $data['card'],
        ]);

        if (! $charge['success']) {
            return response()->json(['message' => $charge['message']], 422);
        }

        $payment = DB::transaction(function () use ($invoice, $amount, $charge, $data, $ledger) {
            $payment = ArPayment::create([
                'ar_invoice_id' => $invoice->id,
                'payment_number' => DocumentNumber::next('ARPAY'),
                'status' => 'completed',
                'method' => 'card',
                'amount' => $amount,
                'paid_at' => now()->toDateString(),
                'reference' => $data['payer_name'] ?? 'Portal payment',
                'authorize_net_trans_id' => $charge['transaction_id'],
                'authorize_net_response' => $charge['raw'],
                'via_portal' => true,
            ]);

            $invoice->recalculateTotals();
            $ledger->postArPayment($payment);

            return $payment;
        });

        return response()->json([
            'data' => [
                'payment_number' => $payment->payment_number,
                'transaction_id' => $payment->authorize_net_trans_id,
                'amount' => $payment->amount,
                'invoice_balance_due' => $invoice->fresh()->balance_due,
                'invoice_status' => $invoice->fresh()->status,
            ],
        ]);
    }

    protected function resolveInvoice(string $token): ArInvoice
    {
        $invoice = ArInvoice::with('lines')
            ->where('portal_token', $token)
            ->where(function ($q) {
                $q->whereNull('portal_token_expires_at')
                    ->orWhere('portal_token_expires_at', '>', now());
            })
            ->first();

        if (! $invoice || in_array($invoice->status, ['void', 'draft'], true)) {
            abort(404, 'Invoice not found or portal link expired.');
        }

        return $invoice;
    }
}
