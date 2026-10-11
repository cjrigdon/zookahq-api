<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpportunityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.opportunities.view');

        $query = Opportunity::with(['account', 'contact', 'owner'])->latest();

        if ($stage = $request->string('stage')->toString()) {
            $query->where('stage', $stage);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.opportunities.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'stage' => ['nullable', 'string', 'max:50'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'event_start_date' => ['nullable', 'date'],
            'event_end_date' => ['nullable', 'date', 'after_or_equal:event_start_date'],
            'venue_space' => ['nullable', 'string', 'max:255'],
            'expected_attendance' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['owner_user_id'] = $data['owner_user_id'] ?? auth()->id();
        $data['stage'] = $data['stage'] ?? 'prospecting';

        $opportunity = Opportunity::create($data);

        return response()->json(['data' => $opportunity->load(['account', 'contact', 'owner'])], 201);
    }

    public function show(Opportunity $opportunity): JsonResponse
    {
        $this->authorizePermission('booking.opportunities.view');

        return response()->json([
            'data' => $opportunity->load(['account', 'contact', 'owner', 'proposals', 'contracts']),
        ]);
    }

    public function update(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizePermission('booking.opportunities.manage');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'stage' => ['nullable', 'string', 'max:50'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'event_start_date' => ['nullable', 'date'],
            'event_end_date' => ['nullable', 'date'],
            'venue_space' => ['nullable', 'string', 'max:255'],
            'expected_attendance' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $data['updated_by'] = auth()->id();
        $opportunity->update($data);

        return response()->json(['data' => $opportunity->fresh(['account', 'contact', 'owner', 'proposals', 'contracts'])]);
    }

    public function destroy(Opportunity $opportunity): JsonResponse
    {
        $this->authorizePermission('booking.opportunities.manage');
        $opportunity->delete();

        return response()->json(['message' => 'Opportunity deleted']);
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
