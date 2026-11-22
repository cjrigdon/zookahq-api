<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = TenantSetting::query()->first();

        if (! $settings) {
            $settings = TenantSetting::create([
                'company_name' => tenant('name'),
            ]);
        }

        return response()->json(['data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'timezone' => ['sometimes', 'string', 'max:64'],
            'locale' => ['sometimes', 'string', 'max:16'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'branding' => ['nullable', 'array'],
            'branding.theme' => ['nullable', 'string', Rule::in(config('themes.presets'))],
            'branding.lead_intake' => ['nullable', 'array'],
            'branding.lead_intake.enabled' => ['nullable', 'boolean'],
            'branding.lead_intake.title' => ['nullable', 'string', 'max:120'],
            'branding.lead_intake.intro' => ['nullable', 'string', 'max:500'],
            'defaults' => ['nullable', 'array'],
        ]);

        $settings = TenantSetting::query()->first();
        if (isset($data['branding'])) {
            $existing = $settings?->branding ?? [];
            if (isset($data['branding']['lead_intake'])) {
                $data['branding']['lead_intake'] = array_merge(
                    $existing['lead_intake'] ?? [],
                    $data['branding']['lead_intake'],
                );
            }
            $data['branding'] = array_merge($existing, $data['branding']);
        }

        if (! $settings) {
            $settings = TenantSetting::create($data + ['company_name' => tenant('name')]);
        } else {
            $settings->update($data);
        }

        return response()->json(['data' => $settings->fresh()]);
    }
}
