<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Events\EventType;
use Illuminate\Http\JsonResponse;

class EventTypeController extends Controller
{
    use AuthorizesPermissions;

    public function index(): JsonResponse
    {
        $this->authorizePermission('events.events.view');

        $types = EventType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'background_color', 'text_color', 'sort_order']);

        return response()->json(['data' => $types]);
    }
}
