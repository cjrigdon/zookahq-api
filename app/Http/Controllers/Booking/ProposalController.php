<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\Contract;
use App\Models\Booking\Proposal;
use App\Models\Documents\DocumentTemplate;
use App\Services\Documents\Documentables;
use App\Services\Documents\DocumentMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.proposals.view');

        $query = Proposal::with('event')->latest();

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.proposals.manage');

        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
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

        return response()->json(['data' => $proposal->load('event')], 201);
    }

    public function show(Proposal $proposal): JsonResponse
    {
        $this->authorizePermission('booking.proposals.view');

        return response()->json(['data' => $proposal->load(['event', 'contract'])]);
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

        return response()->json(['data' => $proposal->fresh('event')]);
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
            'document_template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
        ]);

        $template = null;
        if (! empty($data['document_template_id'])) {
            $template = DocumentTemplate::query()->findOrFail($data['document_template_id']);
        } else {
            $template = DocumentTemplate::query()
                ->where('applies_to', 'event')
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->first();
        }

        $contract = Contract::create([
            'proposal_id' => $proposal->id,
            'event_id' => $proposal->event_id,
            'document_template_id' => $template?->id,
            'title' => $data['title'] ?? $proposal->title.' Contract',
            'status' => 'draft',
            'amount' => $data['amount'] ?? $proposal->amount,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $proposal->update(['status' => 'accepted', 'updated_by' => auth()->id()]);

        if ($template && $proposal->event_id) {
            $documentable = Documentables::loadForMerge('event', (int) $proposal->event_id);
            app(DocumentMergeService::class)->generate($template, $documentable, $contract, $contract->title);
            $contract->refresh();
        }

        return response()->json(['data' => $contract->load(['proposal', 'event', 'document', 'documentTemplate'])], 201);
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
