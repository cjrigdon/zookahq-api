<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Events\SpaceAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaceAttributeController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        $attributes = SpaceAttribute::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $attributes]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $this->validated($request);
        $maxSort = (int) SpaceAttribute::query()->max('sort_order');
        $data['sort_order'] = $data['sort_order'] ?? ($maxSort + 1);
        $data['is_active'] = $data['is_active'] ?? true;

        $attribute = SpaceAttribute::create($data);

        return response()->json(['data' => $attribute], 201);
    }

    public function update(Request $request, SpaceAttribute $spaceAttribute): JsonResponse
    {
        $this->authorizeAdmin();

        $spaceAttribute->update($this->validated($request, updating: true));

        return response()->json(['data' => $spaceAttribute->fresh()]);
    }

    public function destroy(SpaceAttribute $spaceAttribute): JsonResponse
    {
        $this->authorizeAdmin();

        $spaceAttribute->delete();

        return response()->json(['message' => 'Space attribute deleted']);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeAdmin(): void
    {
        if (! auth()->user()?->can('admin.spaces.manage')) {
            abort(403, 'Unauthorized.');
        }
    }
}
