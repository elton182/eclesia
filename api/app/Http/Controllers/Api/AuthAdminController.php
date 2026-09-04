<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthAdminController extends Controller
{
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $admin = SuperAdmin::query()
            ->where('email', $request->validated('email'))
            ->first();

        if ($admin === null || ! Hash::check($request->validated('password'), $admin->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 403);
        }

        $token = $admin->createToken('admin')->plainTextToken;

        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'access_token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var SuperAdmin $admin */
        $admin = $request->user();

        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'access_token' => $request->bearerToken(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var SuperAdmin $admin */
        $admin = $request->user();
        $admin->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout efetuado.']);
    }
}
