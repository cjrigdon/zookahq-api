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

class ArBudgetController extends Controller
{
    use AuthorizesPermissions;

    public function index(): JsonResponse
    {
        $this->authorizePermission('accounting.budgets.view');

        return response()->json(['data' => ArBudget::with('glAccount')->latest('period_start')->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('accounting.budgets.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'period_type' => ['nullable', 'string', 'max:50'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'gl_account_id' => ['nullable', 'exists:gl_accounts,id'],
            'budget_amount' => ['required', 'numeric'],
            'actual_amount' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['period_type'] = $data['period_type'] ?? 'month';

        return response()->json(['data' => ArBudget::create($data)->load('glAccount')], 201);
    }

    public function show(ArBudget $arBudget): JsonResponse
    {
        $this->authorizePermission('accounting.budgets.view');

        return response()->json(['data' => $arBudget->load('glAccount')]);
    }

    public function update(Request $request, ArBudget $arBudget): JsonResponse
    {
        $this->authorizePermission('accounting.budgets.manage');
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'period_type' => ['nullable', 'string', 'max:50'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date'],
            'gl_account_id' => ['nullable', 'exists:gl_accounts,id'],
            'budget_amount' => ['sometimes', 'numeric'],
            'actual_amount' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['updated_by'] = auth()->id();
        $arBudget->update($data);

        return response()->json(['data' => $arBudget->fresh('glAccount')]);
    }

    public function destroy(ArBudget $arBudget): JsonResponse
    {
        $this->authorizePermission('accounting.budgets.manage');
        $arBudget->delete();

        return response()->json(['message' => 'Budget deleted']);
    }
}
