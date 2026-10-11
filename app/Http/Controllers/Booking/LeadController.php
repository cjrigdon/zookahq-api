<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\Account;
use App\Models\Booking\Contact;
use App\Models\Booking\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.leads.view');

        $query = Lead::with('owner')->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.leads.manage');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['owner_user_id'] = $data['owner_user_id'] ?? auth()->id();
        $data['status'] = $data['status'] ?? 'new';

        $lead = Lead::create($data);

        return response()->json(['data' => $lead->load('owner')], 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        $this->authorizePermission('booking.leads.view');

        return response()->json(['data' => $lead->load('owner')]);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizePermission('booking.leads.manage');

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $data['updated_by'] = auth()->id();
        $lead->update($data);

        return response()->json(['data' => $lead->fresh('owner')]);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $this->authorizePermission('booking.leads.manage');
        $lead->delete();

        return response()->json(['message' => 'Lead deleted']);
    }

    public function convert(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizePermission('booking.leads.manage');

        $data = $request->validate([
            'account_name' => ['nullable', 'string', 'max:255'],
        ]);

        $account = Account::create([
            'name' => $data['account_name'] ?? ($lead->company ?: trim($lead->first_name.' '.$lead->last_name)),
            'email' => $lead->email,
            'phone' => $lead->phone,
            'owner_user_id' => $lead->owner_user_id,
            'converted_from_lead_id' => $lead->id,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $contact = Contact::create([
            'account_id' => $account->id,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'is_primary' => true,
            'converted_from_lead_id' => $lead->id,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $lead->update([
            'status' => 'converted',
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'data' => [
                'lead' => $lead->fresh(),
                'account' => $account,
                'contact' => $contact,
            ],
        ]);
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
