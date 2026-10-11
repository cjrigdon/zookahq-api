<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Events\ServiceOrder;
use App\Services\EventChangeLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceOrderController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('events.service_orders.view');

        $query = ServiceOrder::with(['event', 'eventFunction'])->latest();

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('events.service_orders.manage');

        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'event_function_id' => ['nullable', 'exists:event_functions,id'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'service_at' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'instructions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'draft';

        $order = ServiceOrder::create($data);

        EventChangeLogger::log(
            $order->event_id,
            "Service order created: {$order->title}",
            'service_order',
            $order->id,
            'create',
        );

        return response()->json(['data' => $order->load(['event', 'eventFunction'])], 201);
    }

    public function show(ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorizePermission('events.service_orders.view');

        return response()->json(['data' => $serviceOrder->load(['event', 'eventFunction'])]);
    }

    public function update(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorizePermission('events.service_orders.manage');

        $data = $request->validate([
            'event_function_id' => ['nullable', 'exists:event_functions,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'service_at' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'instructions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($data['status']) && $data['status'] !== $serviceOrder->status) {
            EventChangeLogger::log(
                $serviceOrder->event_id,
                "Service order status: {$serviceOrder->status} → {$data['status']}",
                'service_order',
                $serviceOrder->id,
                'update',
                'status',
                $serviceOrder->status,
                $data['status'],
            );
        }

        $data['updated_by'] = auth()->id();
        $serviceOrder->update($data);

        return response()->json(['data' => $serviceOrder->fresh(['event', 'eventFunction'])]);
    }

    public function destroy(ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorizePermission('events.service_orders.manage');

        EventChangeLogger::log(
            $serviceOrder->event_id,
            "Service order deleted: {$serviceOrder->title}",
            'service_order',
            $serviceOrder->id,
            'delete',
        );

        $serviceOrder->delete();

        return response()->json(['message' => 'Service order deleted']);
    }
}
