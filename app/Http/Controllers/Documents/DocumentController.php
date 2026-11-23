<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Booking\Contract;
use App\Models\Documents\Document;
use App\Models\Documents\DocumentTemplate;
use App\Services\Documents\Documentables;
use App\Services\Documents\DocumentMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private DocumentMergeService $merger) {}

    public function available(Request $request): JsonResponse
    {
        $this->authorizeView();

        $data = $request->validate([
            'applies_to' => ['required', 'string', Rule::in(DocumentTemplate::APPLIES_TO)],
        ]);

        $templates = DocumentTemplate::query()
            ->where('applies_to', $data['applies_to'])
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeView();

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(Documentables::MAP))],
            'id' => ['required', 'integer'],
        ]);

        Documentables::findOrFail($data['type'], (int) $data['id']);

        $documents = Document::query()
            ->where('documentable_type', $data['type'])
            ->where('documentable_id', $data['id'])
            ->with(['template:id,name,applies_to,kind', 'creator:id,name'])
            ->latest()
            ->get();

        return response()->json(['data' => $documents]);
    }

    public function generate(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'document_template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
            'type' => ['required', 'string', Rule::in(array_keys(Documentables::MAP))],
            'id' => ['required', 'integer'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $documentable = Documentables::loadForMerge($data['type'], (int) $data['id']);
        $template = $this->resolveTemplate($data['document_template_id'] ?? null, $data['type']);
        $contract = isset($data['contract_id']) ? Contract::query()->findOrFail($data['contract_id']) : null;

        if ($contract && (int) $contract->event_id !== (int) $documentable->getKey() && $data['type'] === 'event') {
            throw ValidationException::withMessages([
                'contract_id' => 'Contract must belong to this event.',
            ]);
        }

        $document = $this->merger->generate($template, $documentable, $contract, $data['title'] ?? null);

        return response()->json(['data' => $document], 201);
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorizeView();

        $disk = Storage::disk('local');
        if (! $disk->exists($document->disk_path)) {
            abort(404, 'File not found.');
        }

        return $disk->download($document->disk_path, $document->original_filename, [
            'Content-Type' => $document->mime_type ?: 'application/pdf',
        ]);
    }

    public function email(Request $request, Document $document): JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'to' => ['nullable', 'email'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $documentable = Documentables::loadForMerge($document->documentable_type, (int) $document->documentable_id);
        $document = $this->merger->send(
            $document,
            $documentable,
            $data['to'] ?? null,
            $data['subject'] ?? null,
            $data['body'] ?? null,
        );

        return response()->json(['data' => $document]);
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->authorizeManage();

        $document->delete();

        return response()->json(['message' => 'Document deleted']);
    }

    protected function resolveTemplate(?int $templateId, string $appliesTo): DocumentTemplate
    {
        if ($templateId) {
            $template = DocumentTemplate::query()->findOrFail($templateId);
            if ($template->applies_to !== $appliesTo) {
                throw ValidationException::withMessages([
                    'document_template_id' => 'Template does not apply to this record type.',
                ]);
            }
            if (! $template->is_active) {
                throw ValidationException::withMessages([
                    'document_template_id' => 'Template is inactive.',
                ]);
            }

            return $template;
        }

        $template = DocumentTemplate::query()
            ->where('applies_to', $appliesTo)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        if (! $template) {
            throw ValidationException::withMessages([
                'document_template_id' => 'No active document template is configured for this record type.',
            ]);
        }

        return $template;
    }

    protected function authorizeView(): void
    {
        if (! auth()->user()?->can('booking.documents.view') && ! auth()->user()?->can('booking.documents.manage')) {
            abort(403, 'Unauthorized.');
        }
    }

    protected function authorizeManage(): void
    {
        if (! auth()->user()?->can('booking.documents.manage')) {
            abort(403, 'Unauthorized.');
        }
    }
}
