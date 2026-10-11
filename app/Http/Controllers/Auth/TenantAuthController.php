<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        $user->load('roles', 'permissions');

        $modules = tenant()->tenantModules()
            ->with('module')
            ->where('enabled', true)
            ->get()
            ->pluck('module.code');

        return response()->json([
            'token' => $token,
            'user' => $user,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames(),
            'modules' => $modules,
            'tenant' => [
                'id' => tenant('id'),
                'name' => tenant('name'),
                'slug' => tenant('slug'),
            ],
            'type' => 'tenant',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('roles', 'permissions');

        $modules = tenant()->tenantModules()
            ->with('module')
            ->where('enabled', true)
            ->get()
            ->pluck('module.code');

        return response()->json([
            'user' => $user,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames(),
            'modules' => $modules,
            'tenant' => [
                'id' => tenant('id'),
                'name' => tenant('name'),
                'slug' => tenant('slug'),
            ],
            'type' => 'tenant',
        ]);
    }
}
