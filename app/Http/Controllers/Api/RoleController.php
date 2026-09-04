<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Role::query()
            ->select(['id', 'code', 'name', 'description', 'created_at', 'updated_at'])
            ->with(['permissions:id,code,name'])
            ->withCount('users')
            ->orderBy('code');

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $this->syncPermissions($role, $data['permission_ids'] ?? []);

        return response()->json([
            'message' => 'Rôle créé avec succès.',
            'data' => $role->fresh()->load(['permissions:id,code,name,module'])->loadCount('users'),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'data' => $role->load(['permissions:id,code,name,module'])->loadCount('users'),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $data = $request->validated();

        $role->update(array_filter([
            'code' => $data['code'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : null,
        ], static fn ($value) => $value !== null));

        if (array_key_exists('permission_ids', $data)) {
            $this->syncPermissions($role, $data['permission_ids'] ?? []);
        }

        return response()->json([
            'message' => 'Rôle mis à jour.',
            'data' => $role->fresh()->load(['permissions:id,code,name,module'])->loadCount('users'),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un rôle utilisé par des utilisateurs.',
            ], 422);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json(['message' => 'Rôle supprimé.']);
    }

    private function syncPermissions(Role $role, array $permissionIds): void
    {
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));

        if ($permissionIds === []) {
            $role->permissions()->detach();

            return;
        }

        $existingPermissionIds = Permission::query()
            ->whereIn('id', $permissionIds)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($existingPermissionIds);
    }
}
