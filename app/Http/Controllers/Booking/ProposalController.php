<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\Contract;
use App\Models\Booking\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.proposals.view');

        $query = Proposal::with('opportunity')->latest();

        if ($opportunityId = $request->integer('opportunity_id')) {
            $query->where('opportunity_id', $opportunityId);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.proposals.manage');

        $data = $request->validate([
            'opportunity_id' => ['required', 'exists:opportunities,id'],
            'title' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'valid_until' => ['nullable', 'date'],
            'terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'draft';
        $data['version'] = $data['version'] ?? 1;

        $proposal = Proposal::create($data);

        return response()->json(['data' => $proposal->load('opportunity')], 201);
    }

    public function show(Proposal $proposal): JsonResponse
    {
        $this->authorizePermission('booking.proposals.view');

        return response()->json(['data' => $proposal->load(['opportunity', 'contract'])]);
    }

    public function update(Request $request, Proposal $proposal): JsonResponse
    {
        $this->authorizePermission('booking.proposals.manage');

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'version' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'valid_until' => ['nullable', 'date'],
            'terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['updated_by'] = auth()->id();
        $proposal->update($data);

        return response()->json(['data' => $proposal->fresh('opportunity')]);
    }

    public function destroy(Proposal $proposal): JsonResponse
    {
        $this->authorizePermission('booking.proposals.manage');
        $proposal->delete();

        return response()->json(['message' => 'Proposal deleted']);
    }

    public function createContract(Request $request, Proposal $proposal): JsonResponse
    {
        $this->authorizePermission('booking.contracts.manage');

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $contract = Contract::create([
            'proposal_id' => $proposal->id,
            'opportunity_id' => $proposal->opportunity_id,
            'title' => $data['title'] ?? $proposal->title.' Contract',
            'status' => 'draft',
            'amount' => $data['amount'] ?? $proposal->amount,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $proposal->update(['status' => 'accepted', 'updated_by' => auth()->id()]);

        return response()->json(['data' => $contract->load(['proposal', 'opportunity'])], 201);
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
