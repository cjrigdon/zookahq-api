<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.accounts.view');

        $query = Account::with(['owner', 'contacts'])->latest();

        if ($search = $request->string('search')->toString()) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('website', 'like', $like)
                    ->orWhere('billing_address', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('state', 'like', $like)
                    ->orWhere('postal_code', 'like', $like);
                $digits = preg_replace('/\D+/', '', $search) ?? '';
                if (strlen($digits) >= 3) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), '-', ''), ' ', ''), '(', ''), ')', ''), '+', '') like ?",
                        ["%{$digits}%"],
                    );
                }
            });
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.accounts.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['owner_user_id'] = $data['owner_user_id'] ?? auth()->id();

        $account = Account::create($data);

        return response()->json(['data' => $account->load(['owner', 'contacts'])], 201);
    }

    public function show(Account $account): JsonResponse
    {
        $this->authorizePermission('booking.accounts.view');

        return response()->json(['data' => $this->loadAccount($account)]);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $this->authorizePermission('booking.accounts.manage');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $data['updated_by'] = auth()->id();
        $account->update($data);

        return response()->json(['data' => $this->loadAccount($account->fresh())]);
    }

    protected function loadAccount(Account $account): Account
    {
        return $account->load([
            'owner',
            'contacts',
            'events' => fn ($q) => $q->latest('starts_at')->with(['proposals', 'contracts', 'contact']),
            'notes' => fn ($q) => $q->latest()->with(['contact', 'event', 'author']),
        ]);
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->authorizePermission('booking.accounts.manage');
        $account->delete();

        return response()->json(['message' => 'Account deleted']);
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
