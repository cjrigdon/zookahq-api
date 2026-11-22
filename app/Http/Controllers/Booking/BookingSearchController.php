<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\Account;
use App\Models\Booking\Contact;
use App\Models\Booking\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $q = trim($data['q']);
        $limit = (int) ($data['limit'] ?? 10);
        $user = auth()->user();

        $result = [
            'query' => $q,
            'leads' => [],
            'accounts' => [],
            'contacts' => [],
        ];

        if ($user?->can('booking.leads.view')) {
            $result['leads'] = $this->searchLeads($q, $limit);
        }

        if ($user?->can('booking.accounts.view')) {
            $result['accounts'] = $this->searchAccounts($q, $limit);
        }

        if ($user?->can('booking.contacts.view')) {
            $result['contacts'] = $this->searchContacts($q, $limit);
        }

        if (
            $result['leads'] === []
            && $result['accounts'] === []
            && $result['contacts'] === []
            && ! $user?->can('booking.leads.view')
            && ! $user?->can('booking.accounts.view')
            && ! $user?->can('booking.contacts.view')
        ) {
            abort(403, 'Unauthorized.');
        }

        return response()->json(['data' => $result]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function searchLeads(string $q, int $limit): array
    {
        $like = '%'.$q.'%';

        return Lead::query()
            ->where(function (Builder $query) use ($q, $like) {
                $query->where('account_name', 'like', $like)
                    ->orWhere('contact_first_name', 'like', $like)
                    ->orWhere('contact_last_name', 'like', $like)
                    ->orWhereRaw("CONCAT(contact_first_name, ' ', contact_last_name) like ?", [$like])
                    ->orWhere('contact_email', 'like', $like)
                    ->orWhere('contact_title', 'like', $like)
                    ->orWhere('contact_phone', 'like', $like)
                    ->orWhere('source', 'like', $like)
                    ->orWhere('status', 'like', $like);
                $this->applyPhoneMatch($query, 'contact_phone', $q);
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Lead $lead) {
                $name = trim(($lead->contact_first_name ?? '').' '.($lead->contact_last_name ?? ''));

                return [
                    'id' => $lead->id,
                    'type' => 'lead',
                    'title' => $lead->account_name ?: ($name !== '' ? $name : 'Lead #'.$lead->id),
                    'subtitle' => collect([
                        $name !== '' && $lead->account_name ? $name : null,
                        $lead->contact_title,
                        $lead->contact_email,
                        $lead->contact_phone,
                        $lead->status,
                    ])->filter()->implode(' · '),
                    'status' => $lead->status,
                    'email' => $lead->contact_email,
                    'phone' => $lead->contact_phone,
                    'href' => "/booking/leads/{$lead->id}",
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function searchAccounts(string $q, int $limit): array
    {
        $like = '%'.$q.'%';

        return Account::query()
            ->withCount('contacts')
            ->where(function (Builder $query) use ($q, $like) {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('website', 'like', $like)
                    ->orWhere('billing_address', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('state', 'like', $like)
                    ->orWhere('postal_code', 'like', $like)
                    ->orWhere('type', 'like', $like);
                $this->applyPhoneMatch($query, 'phone', $q);
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'type' => 'account',
                'title' => $account->name,
                'subtitle' => collect([
                    $account->email,
                    $account->phone,
                    collect([$account->city, $account->state])->filter()->implode(', ') ?: null,
                    $account->contacts_count ? $account->contacts_count.' contacts' : null,
                ])->filter()->implode(' · '),
                'email' => $account->email,
                'phone' => $account->phone,
                'href' => "/booking/accounts/{$account->id}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function searchContacts(string $q, int $limit): array
    {
        $like = '%'.$q.'%';

        return Contact::query()
            ->with('account:id,name')
            ->where(function (Builder $query) use ($q, $like) {
                $query->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like])
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhereHas('account', function (Builder $accountQuery) use ($like) {
                        $accountQuery->where('name', 'like', $like);
                    });
                $this->applyPhoneMatch($query, 'phone', $q);
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Contact $contact) {
                $name = trim(($contact->first_name ?? '').' '.($contact->last_name ?? ''));

                return [
                    'id' => $contact->id,
                    'type' => 'contact',
                    'title' => $name !== '' ? $name : 'Contact #'.$contact->id,
                    'subtitle' => collect([
                        $contact->title,
                        $contact->email,
                        $contact->phone,
                        $contact->account?->name ? 'Account: '.$contact->account->name : null,
                    ])->filter()->implode(' · '),
                    'account_id' => $contact->account_id,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'href' => "/booking/contacts/{$contact->id}",
                ];
            })
            ->all();
    }

    protected function applyPhoneMatch(Builder $query, string $column, string $q): void
    {
        $digits = preg_replace('/\D+/', '', $q) ?? '';
        if (strlen($digits) < 3) {
            return;
        }

        $query->orWhereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '-', ''), ' ', ''), '(', ''), ')', ''), '+', '') like ?",
            ['%'.$digits.'%'],
        );
    }
}
