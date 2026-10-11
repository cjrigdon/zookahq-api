<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Accounting\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('accounting.inventory.view');
        $query = InventoryItem::query()->latest();
        if ($search = $request->string('search')->toString()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('accounting.inventory.manage');
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64', 'unique:inventory_items,sku'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:32'],
            'quantity_on_hand' => ['nullable', 'numeric'],
            'reorder_level' => ['nullable', 'numeric'],
            'unit_cost' => ['nullable', 'numeric'],
            'unit_price' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $item = InventoryItem::create($data);

        return response()->json(['data' => $item], 201);
    }

    public function show(InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorizePermission('accounting.inventory.view');

        return response()->json(['data' => $inventoryItem]);
    }

    public function update(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorizePermission('accounting.inventory.manage');
        $data = $request->validate([
            'sku' => ['sometimes', 'string', 'max:64', 'unique:inventory_items,sku,'.$inventoryItem->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:32'],
            'quantity_on_hand' => ['nullable', 'numeric'],
            'reorder_level' => ['nullable', 'numeric'],
            'unit_cost' => ['nullable', 'numeric'],
            'unit_price' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['updated_by'] = auth()->id();
        $inventoryItem->update($data);

        return response()->json(['data' => $inventoryItem->fresh()]);
    }

    public function destroy(InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorizePermission('accounting.inventory.manage');
        $inventoryItem->delete();

        return response()->json(['message' => 'Inventory item deleted']);
    }

    public function adjust(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorizePermission('accounting.inventory.manage');
        $data = $request->validate([
            'quantity_delta' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $inventoryItem->quantity_on_hand = (float) $inventoryItem->quantity_on_hand + (float) $data['quantity_delta'];
        if ($inventoryItem->quantity_on_hand < 0) {
            return response()->json(['message' => 'Adjustment would result in negative quantity.'], 422);
        }
        if (! empty($data['notes'])) {
            $inventoryItem->notes = trim(($inventoryItem->notes ? $inventoryItem->notes."\n" : '').$data['notes']);
        }
        $inventoryItem->updated_by = auth()->id();
        $inventoryItem->save();

        return response()->json(['data' => $inventoryItem->fresh()]);
    }
}
