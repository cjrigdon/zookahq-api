<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TenantAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->tokens()->where('name', 'spa')->delete();
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json($this->sessionPayload($user, $token));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->sessionPayload($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return $this->me($request);
    }

    private function sessionPayload(User $user, ?string $token = null): array
    {
        $user->load('roles', 'permissions');

        $modules = tenant()->tenantModules()
            ->with('module')
            ->where('enabled', true)
            ->get()
            ->pluck('module.code');

        $payload = [
            'user' => $user,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames(),
            'modules' => $modules,
            'tenant' => [
                'id' => tenant('id'),
                'name' => tenant('name'),
                'slug' => tenant('slug'),
                'theme' => TenantSetting::query()->first()?->theme() ?? config('themes.default'),
            ],
            'type' => 'tenant',
        ];

        if ($token) {
            $payload['token'] = $token;
        }

        return $payload;
    }
}
