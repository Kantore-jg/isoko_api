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
            ->with('user.role')
            ->orderByDesc('created_at');

        if ($module = $request->string('module')->trim()) {
            $query->where('module', $module->toString());
        }

        if ($action = $request->string('action')->trim()) {
            $query->where('action', $action->toString());
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }
}
