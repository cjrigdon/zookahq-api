<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Operations\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('operations.work_orders.view');

        $query = WorkOrder::with(['assignee', 'requester'])->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($priority = $request->string('priority')->toString()) {
            $query->where('priority', $priority);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where('title', 'like', "%{$search}%");
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('operations.work_orders.manage');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'integer'],
            'due_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'assignee_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['requested_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'open';
        $data['priority'] = $data['priority'] ?? 'normal';

        $workOrder = WorkOrder::create($data);

        return response()->json(['data' => $workOrder->load(['assignee', 'requester'])], 201);
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $this->authorizePermission('operations.work_orders.view');

        return response()->json(['data' => $workOrder->load(['assignee', 'requester'])]);
    }

    public function update(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizePermission('operations.work_orders.manage');

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'integer'],
            'due_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'resolution_notes' => ['nullable', 'string'],
            'assignee_user_id' => ['nullable', 'exists:users,id'],
        ]);

        if (($data['status'] ?? null) === 'completed' && ! $workOrder->completed_at) {
            $data['completed_at'] = now();
        }

        if (isset($data['status']) && $data['status'] !== 'completed') {
            $data['completed_at'] = null;
        }

        $data['updated_by'] = auth()->id();
        $workOrder->update($data);

        return response()->json(['data' => $workOrder->fresh(['assignee', 'requester'])]);
    }

    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        $this->authorizePermission('operations.work_orders.manage');
        $workOrder->delete();

        return response()->json(['message' => 'Work order deleted']);
    }
}
