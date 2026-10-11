<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'defaults' => ['nullable', 'array'],
        ]);

        $settings = TenantSetting::query()->first();
        if (! $settings) {
            $settings = TenantSetting::create($data + ['company_name' => tenant('name')]);
        } else {
            $settings->update($data);
        }

        return response()->json(['data' => $settings->fresh()]);
    }
}
