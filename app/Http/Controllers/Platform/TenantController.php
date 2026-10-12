<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        $tenants = Tenant::with(['domains', 'tenantModules.module'])->latest()->get();

        return response()->json(['data' => $tenants]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'alpha_dash', 'unique:tenants,slug'],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'exists:modules,code'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        $slug = Str::lower($data['slug']);

        $tenant = Tenant::create([
            'id' => $slug,
            'name' => $data['name'],
            'slug' => $slug,
            'status' => $data['status'] ?? 'active',
        ]);

        $tenant->domains()->create([
            'domain' => $slug.'.'.config('app.base_domain'),
        ]);

        $moduleCodes = $data['modules'] ?? ['booking'];
        $modules = Module::whereIn('code', $moduleCodes)->get();

        foreach ($modules as $module) {
            TenantModule::create([
                'tenant_id' => $tenant->id,
                'module_id' => $module->id,
                'enabled' => true,
                'enabled_at' => now(),
            ]);
        }

        // Seed initial admin after DB is created/migrated/seeded
        $tenant->run(function () use ($data) {
            $user = \App\Models\User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ]);
            $user->assignRole('tenant_admin');
        });

        $tenant->load(['domains', 'tenantModules.module']);

        return response()->json(['data' => $tenant], 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load(['domains', 'tenantModules.module']);

        return response()->json(['data' => $tenant]);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
        ]);

        $tenant->update($data);

        return response()->json(['data' => $tenant->fresh(['domains', 'tenantModules.module'])]);
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return response()->json(['message' => 'Tenant deleted']);
    }

    public function syncModules(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*.code' => ['required', 'string', 'exists:modules,code'],
            'modules.*.enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $tenant) {
            foreach ($data['modules'] as $item) {
                $module = Module::where('code', $item['code'])->firstOrFail();
                TenantModule::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'module_id' => $module->id],
                    [
                        'enabled' => $item['enabled'],
                        'enabled_at' => $item['enabled'] ? now() : null,
                    ]
                );
            }
        });

        $tenant->load(['tenantModules.module']);

        return response()->json(['data' => $tenant]);
    }
}
