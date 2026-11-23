<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\Contract;
use App\Models\Documents\DocumentTemplate;
use App\Services\Documents\Documentables;
use App\Services\Documents\DocumentMergeService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContractController extends Controller
{
    public function __construct(private DocumentMergeService $merger) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.contracts.view');

        $query = Contract::with(['proposal', 'event', 'document'])->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function show(Contract $contract): JsonResponse
    {
        $this->authorizePermission('booking.contracts.view');

        return response()->json(['data' => $this->payload($contract)]);
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
            'notes' => ['nullable', 'string'],
            'document_template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
        ]);

        $data['updated_by'] = auth()->id();

        if (($data['status'] ?? null) === 'signed' && empty($data['signed_at'])) {
            $data['signed_at'] = now();
        }

        $contract->update($data);

        if (($data['status'] ?? null) === 'signed') {
            $contract->event?->update([
                'status' => 'confirmed',
                'updated_by' => auth()->id(),
            ]);

            $owner = $contract->fresh('event.owner')?->event?->owner;
            if ($owner) {
                app(NotificationDispatcher::class)->send(
                    $owner,
                    'Contract signed',
                    "{$contract->title} was signed.",
                    actionUrl: "/booking/contracts/{$contract->id}",
                    type: 'contract.signed',
                    data: ['contract_id' => $contract->id],
                );
            }
        }

        return response()->json(['data' => $this->payload($contract)]);
    }

    public function generate(Request $request, Contract $contract): JsonResponse
    {
        $this->authorizePermission('booking.contracts.manage');

        $data = $request->validate([
            'document_template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
        ]);

        $event = $contract->event;
        if (! $event) {
            abort(422, 'Contract has no event to merge against.');
        }

        $documentable = Documentables::loadForMerge('event', $event->id);
        $template = $this->resolveTemplate($data['document_template_id'] ?? $contract->document_template_id);
        $this->merger->generate($template, $documentable, $contract, $contract->title);

        return response()->json(['data' => $this->payload($contract->fresh())]);
    }

    public function email(Request $request, Contract $contract): JsonResponse
    {
        $this->authorizePermission('booking.contracts.manage');

        if (! $contract->document_id) {
            $this->generate($request, $contract);
            $contract->refresh();
        }

        $document = $contract->document;
        if (! $document) {
            abort(422, 'Generate a document before sending.');
        }

        $data = $request->validate([
            'to' => ['nullable', 'email'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $event = $contract->event;
        if (! $event) {
            abort(422, 'Contract has no event.');
        }
        $documentable = Documentables::loadForMerge('event', $event->id);
        $this->merger->send(
            $document,
            $documentable,
            $data['to'] ?? null,
            $data['subject'] ?? null,
            $data['body'] ?? null,
        );

        return response()->json(['data' => $this->payload($contract->fresh())]);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $this->authorizePermission('booking.contracts.manage');
        $contract->delete();

        return response()->json(['message' => 'Contract deleted']);
    }

    protected function resolveTemplate(?int $templateId): DocumentTemplate
    {
        if ($templateId) {
            $template = DocumentTemplate::query()->findOrFail($templateId);
            if ($template->applies_to !== 'event') {
                throw ValidationException::withMessages([
                    'document_template_id' => 'Contract templates must apply to events.',
                ]);
            }

            return $template;
        }

        $template = DocumentTemplate::query()
            ->where('applies_to', 'event')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        if (! $template) {
            throw ValidationException::withMessages([
                'document_template_id' => 'No active event document template is configured.',
            ]);
        }

        return $template;
    }

    protected function payload(Contract $contract): Contract
    {
        return $contract->fresh([
            'proposal',
            'event.account',
            'event.contact',
            'documentTemplate',
            'document.template',
            'document.creator',
        ]);
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
