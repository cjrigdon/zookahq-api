<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Documents\DocumentTemplate;
use App\Services\Documents\DocumentMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        $templates = DocumentTemplate::query()
            ->withCount('documents')
            ->orderBy('applies_to')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function mergeFields(): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json(['data' => DocumentMergeService::catalog()]);
    }

    public function show(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json(['data' => $documentTemplate->load('creator')]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $this->validated($request);
        $file = $request->file('file');
        if (! $file) {
            throw ValidationException::withMessages(['file' => 'A DOCX or PDF file is required.']);
        }

        $kind = $this->kindFromUpload($file->getClientOriginalName(), $file->getMimeType());
        $filename = $file->getClientOriginalName();
        $relative = 'documents/templates/'.Str::uuid().'/'.$filename;
        Storage::disk('local')->putFileAs(dirname($relative), $file, basename($relative));

        $template = DocumentTemplate::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'applies_to' => $data['applies_to'],
            'is_default' => false,
            'is_active' => $data['is_active'] ?? true,
            'kind' => $kind,
            'original_filename' => $filename,
            'disk_path' => $relative,
            'mime_type' => $file->getMimeType() ?: $this->mimeForKind($kind),
            'file_size' => $file->getSize(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        if ($data['is_default'] ?? false) {
            $template->makeDefault();
        }

        return response()->json(['data' => $template->fresh('creator')], 201);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $this->validated($request, updating: true);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $kind = $this->kindFromUpload($file->getClientOriginalName(), $file->getMimeType());
            $filename = $file->getClientOriginalName();
            $relative = 'documents/templates/'.Str::uuid().'/'.$filename;
            Storage::disk('local')->putFileAs(dirname($relative), $file, basename($relative));
            if ($documentTemplate->disk_path) {
                Storage::disk('local')->delete($documentTemplate->disk_path);
            }
            $data['kind'] = $kind;
            $data['original_filename'] = $filename;
            $data['disk_path'] = $relative;
            $data['mime_type'] = $file->getMimeType() ?: $this->mimeForKind($kind);
            $data['file_size'] = $file->getSize();
        }

        $makeDefault = array_key_exists('is_default', $data) ? (bool) $data['is_default'] : null;
        unset($data['is_default']);

        if ($data !== []) {
            $documentTemplate->fill($data);
            $documentTemplate->updated_by = auth()->id();
            $documentTemplate->save();
        }

        if ($makeDefault === true) {
            $documentTemplate->makeDefault();
        } elseif ($makeDefault === false && $documentTemplate->is_default) {
            $documentTemplate->is_default = false;
            $documentTemplate->save();
        }

        return response()->json(['data' => $documentTemplate->fresh('creator')]);
    }

    public function destroy(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeAdmin();

        $documentTemplate->delete();

        return response()->json(['message' => 'Document template deleted']);
    }

    public function download(DocumentTemplate $documentTemplate): StreamedResponse
    {
        $this->authorizeAdmin();

        $disk = Storage::disk('local');
        if (! $disk->exists($documentTemplate->disk_path)) {
            abort(404, 'File not found.');
        }

        return $disk->download($documentTemplate->disk_path, $documentTemplate->original_filename);
    }

    protected function validated(Request $request, bool $updating = false): array
    {
        if ($request->has('is_active')) {
            $request->merge(['is_active' => $request->boolean('is_active')]);
        }
        if ($request->has('is_default')) {
            $request->merge(['is_default' => $request->boolean('is_default')]);
        }

        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'applies_to' => [$updating ? 'sometimes' : 'required', 'string', Rule::in(DocumentTemplate::APPLIES_TO)],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'file' => [$updating ? 'nullable' : 'required', 'file', 'max:10240'],
        ]);
    }

    protected function kindFromUpload(string $filename, ?string $mime): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'docx' || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return DocumentTemplate::KIND_DOCX;
        }
        if ($ext === 'pdf' || $mime === 'application/pdf') {
            return DocumentTemplate::KIND_STATIC;
        }

        throw ValidationException::withMessages([
            'file' => 'Upload a .docx merge template or a static .pdf.',
        ]);
    }

    protected function mimeForKind(string $kind): string
    {
        return $kind === DocumentTemplate::KIND_DOCX
            ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            : 'application/pdf';
    }

    protected function authorizeAdmin(): void
    {
        if (! auth()->user()?->can('admin.documents.manage')) {
            abort(403, 'Unauthorized.');
        }
    }
}
