<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Reporting\SavedReport;
use App\Services\Reporting\ReportCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedReportController extends Controller
{
    use AuthorizesPermissions;

    public function index(): JsonResponse
    {
        $this->authorizePermission('reporting.custom.view');

        return response()->json([
            'data' => SavedReport::with('creator:id,name')
                ->orderByDesc('is_favorite')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('reporting.custom.manage');
        $data = $this->validated($request);

        $report = SavedReport::create($data + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['data' => $report], 201);
    }

    public function show(SavedReport $savedReport): JsonResponse
    {
        $this->authorizePermission('reporting.custom.view');

        return response()->json(['data' => $savedReport->load('creator:id,name')]);
    }

    public function update(Request $request, SavedReport $savedReport): JsonResponse
    {
        $this->authorizePermission('reporting.custom.manage');
        $data = $this->validated($request, partial: true);
        $data['updated_by'] = auth()->id();
        $savedReport->update($data);

        return response()->json(['data' => $savedReport->fresh()]);
    }

    public function destroy(SavedReport $savedReport): JsonResponse
    {
        $this->authorizePermission('reporting.custom.manage');
        $savedReport->delete();

        return response()->json(['message' => 'Report deleted']);
    }

    public function run(Request $request, ReportCatalog $catalog): JsonResponse
    {
        $this->authorizePermission('reporting.custom.view');

        $payload = $request->validate([
            'dataset' => ['required', 'string'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
            'filters' => ['nullable', 'array'],
            'sort_column' => ['nullable', 'string'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'group_by' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return response()->json([
            'data' => $catalog->run(
                dataset: $payload['dataset'],
                columns: $payload['columns'] ?? [],
                filters: $payload['filters'] ?? [],
                sortColumn: $payload['sort_column'] ?? null,
                sortDirection: $payload['sort_direction'] ?? 'desc',
                groupBy: $payload['group_by'] ?? null,
                limit: (int) ($payload['limit'] ?? 200),
            ),
        ]);
    }

    public function runSaved(SavedReport $savedReport, ReportCatalog $catalog): JsonResponse
    {
        $this->authorizePermission('reporting.custom.view');

        return response()->json([
            'data' => $catalog->run(
                dataset: $savedReport->dataset,
                columns: $savedReport->columns ?? [],
                filters: $savedReport->filters ?? [],
                sortColumn: $savedReport->sort_column,
                sortDirection: $savedReport->sort_direction ?? 'desc',
                groupBy: $savedReport->group_by,
            ),
        ]);
    }

    protected function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'dataset' => [$partial ? 'sometimes' : 'required', 'string', 'max:64'],
            'columns' => [$partial ? 'sometimes' : 'required', 'array', 'min:1'],
            'columns.*' => ['string'],
            'filters' => ['nullable', 'array'],
            'sort_column' => ['nullable', 'string'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'chart_type' => ['nullable', 'in:none,bar,pie,line'],
            'group_by' => ['nullable', 'string'],
            'ai_prompt' => ['nullable', 'string'],
            'is_favorite' => ['nullable', 'boolean'],
        ];

        return $request->validate($rules);
    }
}
