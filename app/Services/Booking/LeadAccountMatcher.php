<?php

namespace App\Services\Booking;

use App\Models\Booking\Account;
use App\Models\Booking\Contact;
use App\Models\Booking\Lead;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LeadAccountMatcher
{
    /** Minimum score to auto-attach a lead to an existing account on convert. */
    public const AUTO_ATTACH_SCORE = 85;

    /**
     * @return Collection<int, array{account: Account, score: int, reasons: list<string>}>
     */
    public function matches(Lead $lead, int $limit = 8): Collection
    {
        $accountName = trim((string) $lead->account_name);
        $email = strtolower(trim((string) $lead->contact_email));
        $domain = $this->emailDomain($email);

        $candidates = collect();

        if ($email !== '') {
            $byContactEmail = Contact::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->with('account')
                ->get();

            foreach ($byContactEmail as $contact) {
                if (! $contact->account) {
                    continue;
                }
                $candidates = $this->bump(
                    $candidates,
                    $contact->account,
                    100,
                    'Contact email matches '.$contact->first_name.' '.$contact->last_name,
                );
            }

            $byAccountEmail = Account::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->get();

            foreach ($byAccountEmail as $account) {
                $candidates = $this->bump($candidates, $account, 90, 'Account email matches');
            }
        }

        if ($accountName !== '') {
            $exact = Account::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($accountName)])
                ->get();

            foreach ($exact as $account) {
                $candidates = $this->bump($candidates, $account, 85, 'Account name exact match');
            }

            $like = Account::query()
                ->where('name', 'like', '%'.$accountName.'%')
                ->limit(20)
                ->get();

            foreach ($like as $account) {
                $candidates = $this->bump($candidates, $account, 60, 'Account name similar');
            }
        }

        if ($domain !== null && ! $this->isPublicEmailDomain($domain)) {
            $domainAccounts = Account::query()
                ->where(function ($q) use ($domain) {
                    $q->whereRaw('LOWER(email) like ?', ['%@'.$domain])
                        ->orWhereHas('contacts', function ($cq) use ($domain) {
                            $cq->whereRaw('LOWER(email) like ?', ['%@'.$domain]);
                        });
                })
                ->limit(20)
                ->get();

            foreach ($domainAccounts as $account) {
                $candidates = $this->bump($candidates, $account, 70, 'Email domain matches @'.$domain);
            }
        }

        return $candidates
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->map(fn ($row) => [
                'account' => $row['account']->loadCount('contacts'),
                'score' => $row['score'],
                'reasons' => array_values(array_unique($row['reasons'])),
            ]);
    }

    /**
     * @param  Collection<string, array{account: Account, score: int, reasons: list<string>}>  $candidates
     * @return Collection<string, array{account: Account, score: int, reasons: list<string>}>
     */
    protected function bump(Collection $candidates, Account $account, int $score, string $reason): Collection
    {
        $key = (string) $account->id;
        if (! $candidates->has($key)) {
            $candidates[$key] = [
                'account' => $account,
                'score' => $score,
                'reasons' => [$reason],
            ];

            return $candidates;
        }

        $existing = $candidates[$key];
        $existing['score'] = max($existing['score'], $score);
        $existing['reasons'][] = $reason;
        $candidates[$key] = $existing;

        return $candidates;
    }

    protected function emailDomain(string $email): ?string
    {
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        return Str::lower(Str::after($email, '@'));
    }

    protected function isPublicEmailDomain(string $domain): bool
    {
        return in_array($domain, [
            'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com',
            'outlook.com', 'live.com', 'icloud.com', 'me.com', 'aol.com', 'msn.com',
            'proton.me', 'protonmail.com', 'mail.com',
        ], true);
    }
}
