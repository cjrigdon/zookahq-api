<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Events\SpaceStyle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SpaceStyleController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        $styles = SpaceStyle::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $styles]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $this->validated($request);
        $maxSort = (int) SpaceStyle::query()->max('sort_order');
        $data['sort_order'] = $data['sort_order'] ?? ($maxSort + 1);
        $data['is_active'] = $data['is_active'] ?? true;
        $data['code'] = $this->normalizeCode($data['code'] ?? null, $data['name']);

        $style = SpaceStyle::create($data);

        return response()->json(['data' => $style], 201);
    }

    public function update(Request $request, SpaceStyle $spaceStyle): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $this->validated($request, updating: true, styleId: $spaceStyle->id);
        if (array_key_exists('code', $data)) {
            $data['code'] = $this->normalizeCode(
                $data['code'],
                $data['name'] ?? $spaceStyle->name,
            );
        }

        $spaceStyle->update($data);

        return response()->json(['data' => $spaceStyle->fresh()]);
    }

    public function destroy(SpaceStyle $spaceStyle): JsonResponse
    {
        $this->authorizeAdmin();

        $spaceStyle->delete();

        return response()->json(['message' => 'Space style deleted']);
    }

    private function validated(Request $request, bool $updating = false, ?int $styleId = null): array
    {
        return $request->validate([
            'code' => [
                $updating ? 'sometimes' : 'nullable',
                'string',
                'max:64',
                Rule::unique('space_styles', 'code')->ignore($styleId),
            ],
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function normalizeCode(?string $code, string $name): string
    {
        $value = $code !== null && trim($code) !== ''
            ? Str::upper(Str::slug(trim($code), '_'))
            : Str::upper(Str::slug($name, '_'));

        return $value !== '' ? substr($value, 0, 64) : 'STYLE';
    }

    private function authorizeAdmin(): void
    {
        if (! auth()->user()?->can('admin.spaces.manage')) {
            abort(403, 'Unauthorized.');
        }
    }
}
