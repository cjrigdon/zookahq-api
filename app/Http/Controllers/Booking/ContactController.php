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
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
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
