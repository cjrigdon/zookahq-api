<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.contacts.view');

        $query = Contact::with('account')->latest();

        if ($accountId = $request->integer('account_id')) {
            $query->where('account_id', $accountId);
        }

        if ($search = $request->string('search')->toString()) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($search, $like) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like])
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('title', 'like', $like);
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
        $this->authorizePermission('booking.contacts.manage');

        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $contact = Contact::create($data);

        return response()->json(['data' => $contact->load('account')], 201);
    }

    public function show(Contact $contact): JsonResponse
    {
        $this->authorizePermission('booking.contacts.view');

        return response()->json(['data' => $contact->load('account')]);
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizePermission('booking.contacts.manage');

        $data = $request->validate([
            'account_id' => ['sometimes', 'exists:accounts,id'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $data['updated_by'] = auth()->id();
        $contact->update($data);

        return response()->json(['data' => $contact->fresh('account')]);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->authorizePermission('booking.contacts.manage');
        $contact->delete();

        return response()->json(['message' => 'Contact deleted']);
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
