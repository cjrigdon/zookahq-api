<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\Account;
use App\Models\Booking\AccountNote;
use App\Models\Booking\Contact;
use App\Models\Events\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountNoteController extends Controller
{
    public function index(Account $account): JsonResponse
    {
        $this->authorizePermission('booking.accounts.view');

        $notes = $account->notes()
            ->with(['contact', 'event', 'author'])
            ->latest()
            ->get();

        return response()->json(['data' => $notes]);
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->authorizePermission('booking.accounts.manage');

        $data = $this->validated($request, $account);
        $data['account_id'] = $account->id;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $note = AccountNote::create($data);

        return response()->json(['data' => $note->load(['contact', 'event', 'author'])], 201);
    }

    public function update(Request $request, Account $account, AccountNote $note): JsonResponse
    {
        $this->authorizePermission('booking.accounts.manage');
        $this->ensureNoteBelongsToAccount($account, $note);

        $data = $this->validated($request, $account, updating: true);
        $data['updated_by'] = auth()->id();
        $note->update($data);

        return response()->json(['data' => $note->fresh(['contact', 'event', 'author'])]);
    }

    public function destroy(Account $account, AccountNote $note): JsonResponse
    {
        $this->authorizePermission('booking.accounts.manage');
        $this->ensureNoteBelongsToAccount($account, $note);
        $note->delete();

        return response()->json(['message' => 'Note deleted']);
    }

    protected function validated(Request $request, Account $account, bool $updating = false): array
    {
        $data = $request->validate([
            'body' => [$updating ? 'sometimes' : 'required', 'string'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);

        if (array_key_exists('contact_id', $data) && $data['contact_id']) {
            $belongs = Contact::query()
                ->where('id', $data['contact_id'])
                ->where('account_id', $account->id)
                ->exists();
            if (! $belongs) {
                throw ValidationException::withMessages([
                    'contact_id' => 'Contact must belong to this account.',
                ]);
            }
        }

        if (array_key_exists('event_id', $data) && $data['event_id']) {
            $belongs = Event::query()
                ->where('id', $data['event_id'])
                ->where('account_id', $account->id)
                ->exists();
            if (! $belongs) {
                throw ValidationException::withMessages([
                    'event_id' => 'Event must belong to this account.',
                ]);
            }
        }

        return $data;
    }

    protected function ensureNoteBelongsToAccount(Account $account, AccountNote $note): void
    {
        if ((int) $note->account_id !== (int) $account->id) {
            abort(404);
        }
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
