<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Events\Space;
use App\Models\Events\SpaceAttribute;
use App\Models\Events\SpaceAttributeValue;
use App\Models\Events\SpaceCapacity;
use App\Models\Events\SpaceStyle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SpaceController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        $spaces = Space::query()
            ->with(['capacities.style', 'attributeValues.attribute'])
            ->withCount('capacities')
            ->orderBy('name')
            ->get()
            ->map(fn (Space $space) => $this->serialize($space));

        return response()->json(['data' => $spaces]);
    }

    public function show(Space $space): JsonResponse
    {
        $this->authorizeAdmin();

        $space->load(['capacities.style', 'attributeValues.attribute']);

        return response()->json([
            'data' => $this->serialize($space),
            'attributes' => SpaceAttribute::query()->orderBy('sort_order')->orderBy('name')->get(),
            'styles' => SpaceStyle::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $this->validated($request);
        $styles = $data['styles'] ?? [];
        $attributes = $data['attributes'] ?? [];
        unset($data['styles'], $data['attributes']);

        $space = DB::transaction(function () use ($data, $styles, $attributes) {
            $space = Space::create([
                ...$data,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            $this->syncCapacities($space, $styles);
            $this->syncAttributes($space, $attributes);

            return $space->fresh(['capacities.style', 'attributeValues.attribute']);
        });

        return response()->json(['data' => $this->serialize($space)], 201);
    }

    public function update(Request $request, Space $space): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $this->validated($request, updating: true);
        $styles = array_key_exists('styles', $data) ? $data['styles'] : null;
        $attributes = array_key_exists('attributes', $data) ? $data['attributes'] : null;
        unset($data['styles'], $data['attributes']);

        $space = DB::transaction(function () use ($space, $data, $styles, $attributes) {
            if ($data !== []) {
                $space->fill($data);
                $space->updated_by = auth()->id();
                $space->save();
            }

            if (is_array($styles)) {
                $this->syncCapacities($space, $styles);
            }

            if (is_array($attributes)) {
                $this->syncAttributes($space, $attributes);
            }

            return $space->fresh(['capacities.style', 'attributeValues.attribute']);
        });

        return response()->json(['data' => $this->serialize($space)]);
    }

    public function destroy(Space $space): JsonResponse
    {
        $this->authorizeAdmin();

        $space->delete();

        return response()->json(['message' => 'Space deleted']);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'area_sq_ft' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'is_active' => ['nullable', 'boolean'],
            'styles' => [$updating ? 'sometimes' : 'nullable', 'array'],
            'styles.*.id' => ['nullable', 'integer'],
            'styles.*.space_style_id' => [
                'required_with:styles',
                'integer',
                Rule::exists('space_styles', 'id'),
            ],
            'styles.*.seating_capacity' => ['required_with:styles', 'integer', 'min:0'],
            'styles.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'attributes' => ['nullable', 'array'],
            'attributes.*.space_attribute_id' => ['required_with:attributes', 'integer', 'exists:space_attributes,id'],
            'attributes.*.value' => ['required_with:attributes', 'boolean'],
        ]);
    }

    private function syncCapacities(Space $space, array $styles): void
    {
        $keepIds = [];
        $seenStyleIds = [];

        foreach (array_values($styles) as $index => $styleData) {
            $styleId = (int) $styleData['space_style_id'];
            if (isset($seenStyleIds[$styleId])) {
                continue;
            }
            $seenStyleIds[$styleId] = true;

            $payload = [
                'space_style_id' => $styleId,
                'seating_capacity' => (int) $styleData['seating_capacity'],
                'sort_order' => $styleData['sort_order'] ?? $index,
            ];

            if (! empty($styleData['id'])) {
                $capacity = SpaceCapacity::query()
                    ->where('space_id', $space->id)
                    ->where('id', $styleData['id'])
                    ->first();
                if ($capacity) {
                    $capacity->update($payload);
                    $keepIds[] = $capacity->id;
                    continue;
                }
            }

            $existing = SpaceCapacity::query()
                ->where('space_id', $space->id)
                ->where('space_style_id', $styleId)
                ->first();

            if ($existing) {
                $existing->update($payload);
                $keepIds[] = $existing->id;
                continue;
            }

            $capacity = $space->capacities()->create($payload);
            $keepIds[] = $capacity->id;
        }

        $space->capacities()->whereNotIn('id', $keepIds)->delete();
    }

    private function syncAttributes(Space $space, array $attributes): void
    {
        foreach ($attributes as $attributeData) {
            SpaceAttributeValue::query()->updateOrCreate(
                [
                    'space_id' => $space->id,
                    'space_attribute_id' => $attributeData['space_attribute_id'],
                ],
                ['value' => (bool) $attributeData['value']],
            );
        }
    }

    private function serialize(Space $space): array
    {
        $attributeMap = $space->attributeValues
            ->keyBy('space_attribute_id')
            ->map(fn (SpaceAttributeValue $value) => [
                'space_attribute_id' => $value->space_attribute_id,
                'name' => $value->attribute?->name,
                'value' => (bool) $value->value,
            ])
            ->values();

        $capacities = $space->capacities;

        return [
            'id' => $space->id,
            'name' => $space->name,
            'description' => $space->description,
            'area_sq_ft' => $space->area_sq_ft,
            'is_active' => $space->is_active,
            'styles_count' => $space->capacities_count ?? $capacities->count(),
            'max_capacity' => (int) $capacities->max('seating_capacity'),
            'styles' => $capacities->map(fn (SpaceCapacity $capacity) => [
                'id' => $capacity->id,
                'space_style_id' => $capacity->space_style_id,
                'code' => $capacity->style?->code,
                'name' => $capacity->style?->name,
                'description' => $capacity->style?->description,
                'seating_capacity' => $capacity->seating_capacity,
                'sort_order' => $capacity->sort_order,
            ])->values(),
            'attributes' => $attributeMap,
            'created_at' => $space->created_at,
            'updated_at' => $space->updated_at,
        ];
    }

    private function authorizeAdmin(): void
    {
        if (! auth()->user()?->can('admin.spaces.manage')) {
            abort(403, 'Unauthorized.');
        }
    }
}
