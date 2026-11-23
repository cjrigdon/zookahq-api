<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\Account;
use App\Models\Booking\Contact;
use App\Models\Booking\Lead;
use App\Services\Booking\LeadAccountMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('booking.leads.view');

        $query = Lead::with('owner')->latest();

        if ($search = $request->string('search')->toString()) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($search, $like) {
                $q->where('contact_first_name', 'like', $like)
                    ->orWhere('contact_last_name', 'like', $like)
                    ->orWhereRaw("CONCAT(contact_first_name, ' ', contact_last_name) like ?", [$like])
                    ->orWhere('contact_email', 'like', $like)
                    ->orWhere('contact_title', 'like', $like)
                    ->orWhere('contact_phone', 'like', $like)
                    ->orWhere('account_name', 'like', $like);
                $digits = preg_replace('/\D+/', '', $search) ?? '';
                if (strlen($digits) >= 3) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(contact_phone, ''), '-', ''), ' ', ''), '(', ''), ')', ''), '+', '') like ?",
                        ["%{$digits}%"],
                    );
                }
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

        $data = $request->validate($this->leadRules(creating: true));

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['owner_user_id'] = $data['owner_user_id'] ?? auth()->id();
        $data['status'] = $data['status'] ?? 'unqualified';

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

        $data = $request->validate($this->leadRules(creating: false));

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

    public function matches(Lead $lead, LeadAccountMatcher $matcher): JsonResponse
    {
        $this->authorizePermission('booking.leads.view');

        return response()->json(['data' => $matcher->matches($lead)->values()]);
    }

    public function attach(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizePermission('booking.leads.manage');

        if ($lead->status === 'converted') {
            abort(422, 'Lead is already converted.');
        }

        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
        ]);

        $result = DB::transaction(function () use ($lead, $data) {
            $account = Account::query()->findOrFail($data['account_id']);
            $hasPrimary = $account->contacts()->where('is_primary', true)->exists();

            $contact = Contact::create([
                'account_id' => $account->id,
                'first_name' => $lead->contact_first_name,
                'last_name' => $lead->contact_last_name,
                'email' => $lead->contact_email,
                'phone' => $lead->contact_phone,
                'title' => $lead->contact_title,
                'is_primary' => ! $hasPrimary,
                'converted_from_lead_id' => $lead->id,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $lead->update([
                'status' => 'converted',
                'updated_by' => auth()->id(),
            ]);

            return [
                'lead' => $lead->fresh(),
                'account' => $account->fresh(['contacts']),
                'contact' => $contact,
                'matched' => true,
            ];
        });

        return response()->json(['data' => $result]);
    }

    public function convert(Request $request, Lead $lead, LeadAccountMatcher $matcher): JsonResponse
    {
        $this->authorizePermission('booking.leads.manage');

        if ($lead->status === 'converted') {
            abort(422, 'Lead is already converted.');
        }

        $data = $request->validate([
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'force_new' => ['nullable', 'boolean'],
        ]);

        // Prefer an explicit account, otherwise auto-match on convert.
        if (! ($data['force_new'] ?? false)) {
            $accountId = $data['account_id'] ?? null;
            if (! $accountId) {
                $best = $matcher->matches($lead, 1)->first();
                if ($best && ($best['score'] ?? 0) >= LeadAccountMatcher::AUTO_ATTACH_SCORE) {
                    $accountId = $best['account']->id;
                }
            }

            if ($accountId) {
                $request->merge(['account_id' => $accountId]);

                return $this->attach($request, $lead);
            }
        }

        $result = DB::transaction(function () use ($lead, $data) {
            $contactName = trim($lead->contact_first_name.' '.$lead->contact_last_name);

            $account = Account::create([
                'name' => $data['account_name'] ?? ($lead->account_name ?: $contactName),
                'email' => $lead->contact_email,
                'phone' => $lead->contact_phone,
                'billing_address' => $lead->account_address,
                'address_2' => $lead->account_address_2,
                'city' => $lead->account_city,
                'state' => $lead->account_state,
                'postal_code' => $lead->account_postal_code,
                'owner_user_id' => $lead->owner_user_id,
                'converted_from_lead_id' => $lead->id,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $contact = Contact::create([
                'account_id' => $account->id,
                'first_name' => $lead->contact_first_name,
                'last_name' => $lead->contact_last_name,
                'email' => $lead->contact_email,
                'phone' => $lead->contact_phone,
                'title' => $lead->contact_title,
                'is_primary' => true,
                'converted_from_lead_id' => $lead->id,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $lead->update([
                'status' => 'converted',
                'updated_by' => auth()->id(),
            ]);

            return [
                'lead' => $lead->fresh(),
                'account' => $account,
                'contact' => $contact,
                'matched' => false,
            ];
        });

        return response()->json(['data' => $result]);
    }

    protected function leadRules(bool $creating): array
    {
        $nameRule = $creating ? 'required' : 'sometimes';

        return [
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_address' => ['nullable', 'string', 'max:255'],
            'account_address_2' => ['nullable', 'string', 'max:255'],
            'account_city' => ['nullable', 'string', 'max:100'],
            'account_state' => ['nullable', 'string', 'max:100'],
            'account_postal_code' => ['nullable', 'string', 'max:20'],
            'contact_first_name' => [$nameRule, 'string', 'max:255'],
            'contact_last_name' => [$nameRule, 'string', 'max:255'],
            'contact_email' => ['nullable', 'email'],
            'contact_title' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'event_date' => ['nullable', 'date'],
            'load_in_at' => ['nullable', 'date'],
            'event_starts_at' => ['nullable', 'date'],
            'event_ends_at' => ['nullable', 'date'],
            'load_out_at' => ['nullable', 'date'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ];
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
