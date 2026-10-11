<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\SalesGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesGoalController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizePermission('booking.sales_goals.view');

        $goals = SalesGoal::with('owner')->latest('period_start')->paginate(25);

        return response()->json(['data' => $goals]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.sales_goals.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'period_type' => ['nullable', 'string', 'max:50'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'target_amount' => ['required', 'numeric', 'min:0'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['owner_user_id'] = $data['owner_user_id'] ?? auth()->id();
        $data['period_type'] = $data['period_type'] ?? 'month';

        $goal = SalesGoal::create($data);

        return response()->json(['data' => $goal->load('owner')], 201);
    }

    public function show(SalesGoal $salesGoal): JsonResponse
    {
        $this->authorizePermission('booking.sales_goals.view');

        return response()->json(['data' => $salesGoal->load('owner')]);
    }

    public function update(Request $request, SalesGoal $salesGoal): JsonResponse
    {
        $this->authorizePermission('booking.sales_goals.manage');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'period_type' => ['nullable', 'string', 'max:50'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date'],
            'target_amount' => ['sometimes', 'numeric', 'min:0'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['updated_by'] = auth()->id();
        $salesGoal->update($data);

        return response()->json(['data' => $salesGoal->fresh('owner')]);
    }

    public function destroy(SalesGoal $salesGoal): JsonResponse
    {
        $this->authorizePermission('booking.sales_goals.manage');
        $salesGoal->delete();

        return response()->json(['message' => 'Sales goal deleted']);
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
