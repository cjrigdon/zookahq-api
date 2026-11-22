<?php

namespace App\Http\Controllers\Intake;

use App\Http\Controllers\Controller;
use App\Models\Booking\Lead;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeadIntakeController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = $this->settings();
        $intake = $settings->leadIntake();

        return response()->json([
            'data' => [
                'enabled' => $intake['enabled'],
                'tenant_name' => $settings->company_name ?: tenant('name'),
                'theme' => $settings->theme(),
                'title' => $intake['title'],
                'intro' => $intake['intro'],
            ],
        ]);
    }

    public function store(Request $request, NotificationDispatcher $dispatcher): JsonResponse
    {
        $settings = $this->settings();
        $intake = $settings->leadIntake();

        if (! $intake['enabled']) {
            abort(403, 'Lead intake is not enabled for this venue.');
        }

        if (filled($request->input('website'))) {
            return response()->json(['data' => ['received' => true]], 201);
        }

        $data = $request->validate([
            'account_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_first_name' => ['nullable', 'string', 'max:255'],
            'contact_last_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_title' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'event_date' => ['required', 'date'],
            'load_in_at' => ['nullable', 'date'],
            'event_starts_at' => ['nullable', 'date'],
            'event_ends_at' => ['nullable', 'date', 'after_or_equal:event_starts_at'],
            'load_out_at' => ['nullable', 'date', 'after_or_equal:event_ends_at'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        [$firstName, $lastName] = $this->resolveContactName($data);

        $owner = User::role(['tenant_admin', 'sales'])->first();

        $lead = Lead::create([
            'account_name' => $data['account_name'],
            'contact_first_name' => $firstName,
            'contact_last_name' => $lastName,
            'contact_email' => $data['contact_email'],
            'contact_title' => $data['contact_title'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'source' => 'website',
            'status' => 'unqualified',
            'notes' => filled($data['message'] ?? null) ? $data['message'] : null,
            'event_date' => $data['event_date'],
            'load_in_at' => $data['load_in_at'] ?? null,
            'event_starts_at' => $data['event_starts_at'] ?? null,
            'event_ends_at' => $data['event_ends_at'] ?? null,
            'load_out_at' => $data['load_out_at'] ?? null,
            'owner_user_id' => $owner?->id,
        ]);

        $this->notifyStaff($dispatcher, $lead);

        return response()->json(['data' => ['received' => true]], 201);
    }

    private function settings(): TenantSetting
    {
        $settings = TenantSetting::query()->first();

        if ($settings) {
            return $settings;
        }

        return TenantSetting::create([
            'company_name' => tenant('name'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string}
     */
    private function resolveContactName(array $data): array
    {
        if (filled($data['contact_name'] ?? null)) {
            $parts = preg_split('/\s+/', trim((string) $data['contact_name'])) ?: [];
            if ($parts === []) {
                throw ValidationException::withMessages([
                    'contact_name' => 'Contact name is required.',
                ]);
            }

            if (count($parts) === 1) {
                return [$parts[0], $parts[0]];
            }

            $last = array_pop($parts);

            return [implode(' ', $parts), $last];
        }

        $first = trim((string) ($data['contact_first_name'] ?? ''));
        $last = trim((string) ($data['contact_last_name'] ?? ''));

        if ($first === '' || $last === '') {
            $messages = [];
            if ($first === '') {
                $messages['contact_first_name'] = 'First name is required.';
            }
            if ($last === '') {
                $messages['contact_last_name'] = 'Last name is required.';
            }

            throw ValidationException::withMessages($messages);
        }

        return [$first, $last];
    }

    private function notifyStaff(NotificationDispatcher $dispatcher, Lead $lead): void
    {
        $staff = User::role(['tenant_admin', 'sales'])->get();
        $name = trim($lead->contact_first_name.' '.$lead->contact_last_name);

        foreach ($staff as $user) {
            $dispatcher->send(
                $user,
                'New website lead',
                "{$name} submitted a request from the intake form.",
                actionUrl: "/booking/leads/{$lead->id}",
                type: 'lead.intake',
                data: ['lead_id' => $lead->id],
            );
        }
    }
}
