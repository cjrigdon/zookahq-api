<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Accounting\ArInvoice;
use App\Models\Accounting\ArInvoiceLine;
use App\Services\Accounting\DocumentNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArInvoiceController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('accounting.ar_invoices.view');
        $query = ArInvoice::with('lines')->latest();
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('accounting.ar_invoices.manage');
        $data = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'event_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'bill_to_name' => ['nullable', 'string', 'max:255'],
            'bill_to_email' => ['nullable', 'email'],
            'tax_total' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric'],
            'lines.*.inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
        ]);

        $invoice = DB::transaction(function () use ($data) {
            $invoice = ArInvoice::create([
                'invoice_number' => DocumentNumber::next('INV'),
                'account_id' => $data['account_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'bill_to_name' => $data['bill_to_name'] ?? null,
                'bill_to_email' => $data['bill_to_email'] ?? null,
                'tax_total' => $data['tax_total'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'portal_token' => Str::random(48),
                'portal_token_expires_at' => now()->addDays(60),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($data['lines'] as $i => $line) {
                $qty = (float) $line['quantity'];
                $price = (float) $line['unit_price'];
                ArInvoiceLine::create([
                    'ar_invoice_id' => $invoice->id,
                    'inventory_item_id' => $line['inventory_item_id'] ?? null,
                    'description' => $line['description'],
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'line_total' => $qty * $price,
                    'sort_order' => $i,
                ]);
            }

            $invoice->recalculateTotals();

            return $invoice->fresh('lines');
        });

        return response()->json(['data' => $invoice], 201);
    }

    public function show(ArInvoice $arInvoice): JsonResponse
    {
        $this->authorizePermission('accounting.ar_invoices.view');

        return response()->json(['data' => $arInvoice->load(['lines', 'payments'])]);
    }

    public function update(Request $request, ArInvoice $arInvoice): JsonResponse
    {
        $this->authorizePermission('accounting.ar_invoices.manage');
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'invoice_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date'],
            'bill_to_name' => ['nullable', 'string', 'max:255'],
            'bill_to_email' => ['nullable', 'email'],
            'tax_total' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['updated_by'] = auth()->id();
        $arInvoice->update($data);
        $arInvoice->recalculateTotals();

        return response()->json(['data' => $arInvoice->fresh(['lines', 'payments'])]);
    }

    public function destroy(ArInvoice $arInvoice): JsonResponse
    {
        $this->authorizePermission('accounting.ar_invoices.manage');
        $arInvoice->delete();

        return response()->json(['message' => 'Invoice deleted']);
    }

    public function issuePortalLink(ArInvoice $arInvoice): JsonResponse
    {
        $this->authorizePermission('accounting.ar_invoices.manage');
        $arInvoice->update([
            'portal_token' => Str::random(48),
            'portal_token_expires_at' => now()->addDays(60),
            'status' => $arInvoice->status === 'draft' ? 'sent' : $arInvoice->status,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'data' => [
                'token' => $arInvoice->portal_token,
                'expires_at' => $arInvoice->portal_token_expires_at,
                'path' => '/portal/invoices/'.$arInvoice->portal_token,
            ],
        ]);
    }
}
