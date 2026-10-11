<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.contracts.view');

        $query = Contract::with(['proposal', 'opportunity'])->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function show(Contract $contract): JsonResponse
    {
        $this->authorizePermission('booking.contracts.view');

        return response()->json(['data' => $contract->load(['proposal', 'opportunity'])]);
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        $this->authorizePermission('booking.contracts.manage');

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'signed_at' => ['nullable', 'date'],
            'signed_by_name' => ['nullable', 'string', 'max:255'],
            'document_path' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['updated_by'] = auth()->id();

        if (($data['status'] ?? null) === 'signed' && empty($data['signed_at'])) {
            $data['signed_at'] = now();
        }

        $contract->update($data);

        if (($data['status'] ?? null) === 'signed') {
            $contract->opportunity?->update([
                'stage' => 'booked',
                'updated_by' => auth()->id(),
            ]);
        }

        return response()->json(['data' => $contract->fresh(['proposal', 'opportunity'])]);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $this->authorizePermission('booking.contracts.manage');
        $contract->delete();

        return response()->json(['message' => 'Contract deleted']);
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
