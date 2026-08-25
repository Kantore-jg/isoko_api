<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->select(['id', 'user_id', 'action', 'module', 'entity_type', 'entity_id', 'old_values', 'new_values', 'created_at', 'updated_at'])
            ->with(['user:id,name,role_id', 'user.role:id,code,name'])
            ->orderByDesc('created_at');

        if ($module = trim((string) $request->string('module'))) {
            $query->where('module', $module);
        }

        if ($action = trim((string) $request->string('action'))) {
            $query->where('action', $action);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }
}
