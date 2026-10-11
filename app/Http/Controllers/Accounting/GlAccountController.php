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

class GlAccountController extends Controller
{
    use AuthorizesPermissions;

    public function index(): JsonResponse
    {
        $this->authorizePermission('accounting.gl.view');

        return response()->json(['data' => GlAccount::orderBy('code')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('accounting.gl.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:gl_accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json(['data' => GlAccount::create($data)], 201);
    }
}
