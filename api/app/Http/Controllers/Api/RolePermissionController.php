<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function roles(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('permissions.view') || $request->user()?->can('roles.assign'), 403);

        setPermissionsTeamId(null);

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return response()->json(['data' => $roles]);
    }

    public function permissions(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('permissions.view'), 403);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return response()->json(['data' => $permissions]);
    }
}
