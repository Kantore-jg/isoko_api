<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query()
            ->withCount('roles')
            ->orderBy('module')
            ->orderBy('code');

        if ($module = $request->string('module')->trim()) {
            $query->where('module', $module->toString());
        }

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100', 'unique:permissions,code'],
            'name' => ['required', 'string', 'max:150'],
            'module' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $permission = Permission::query()->create($data);

        return response()->json([
            'message' => 'Permission créée avec succès.',
            'data' => $permission->loadCount('roles'),
        ], 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'data' => $permission->load('roles'),
        ]);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:100', 'unique:permissions,code,' . $permission->id],
            'name' => ['sometimes', 'string', 'max:150'],
            'module' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $permission->update($data);

        return response()->json([
            'message' => 'Permission mise à jour.',
            'data' => $permission->fresh()->loadCount('roles'),
        ]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        if ($permission->roles()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une permission déjà utilisée.',
            ], 422);
        }

        $permission->delete();

        return response()->json(['message' => 'Permission supprimée.']);
    }
}
