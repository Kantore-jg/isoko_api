<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Place::query()->with(['block:id,code,name'])->orderBy('code');

        if ($blockId = $request->integer('block_id')) {
            $query->where('block_id', $blockId);
        }

        if ($status = $request->string('status')->trim()) {
            $query->where('status', $status->toString());
        }

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'block_id' => ['required', 'exists:blocks,id'],
            'code' => ['required', 'string', 'max:50', 'unique:places,code'],
            'name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'surface' => ['nullable', 'numeric', 'min:0'],
            'type' => ['nullable', Rule::in(['STANDARD', 'KIOSK', 'BOUTIQUE', 'STALL', 'WAREHOUSE', 'OTHER'])],
            'status' => ['nullable', Rule::in(['AVAILABLE', 'OCCUPIED', 'MAINTENANCE', 'INACTIVE'])],
        ]);

        $place = Place::query()->create($data);

        return response()->json([
            'message' => 'Place créée avec succès.',
            'data' => $place->load('block'),
        ], 201);
    }

    public function show(Place $place): JsonResponse
    {
        return response()->json([
            'data' => $place->load(['block', 'assignments.merchant']),
        ]);
    }

    public function update(Request $request, Place $place): JsonResponse
    {
        $data = $request->validate([
            'block_id' => ['sometimes', 'exists:blocks,id'],
            'code' => ['sometimes', 'string', 'max:50', 'unique:places,code,' . $place->id],
            'name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'surface' => ['nullable', 'numeric', 'min:0'],
            'type' => ['sometimes', Rule::in(['STANDARD', 'KIOSK', 'BOUTIQUE', 'STALL', 'WAREHOUSE', 'OTHER'])],
            'status' => ['sometimes', Rule::in(['AVAILABLE', 'OCCUPIED', 'MAINTENANCE', 'INACTIVE'])],
        ]);

        $place->update($data);

        return response()->json([
            'message' => 'Place mise à jour.',
            'data' => $place->fresh()->load('block'),
        ]);
    }

    public function destroy(Place $place): JsonResponse
    {
        if ($place->assignments()->where('status', 'ACTIVE')->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une place occupée.',
            ], 422);
        }

        $place->delete();

        return response()->json(['message' => 'Place supprimée.']);
    }
}
