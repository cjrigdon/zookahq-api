<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Events\EventChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventChangeController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('events.changes.view');

        $query = EventChange::with(['event', 'changedByUser'])->latest();

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        return response()->json(['data' => $query->paginate(50)]);
    }
}
