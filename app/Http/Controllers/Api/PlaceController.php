<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceRequest;
use App\Http\Requests\UpdatePlaceRequest;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Place::query()
            ->select(['id', 'block_id', 'code', 'name', 'description', 'surface', 'type', 'status', 'created_at', 'updated_at'])
            ->with(['block:id,code,name,default_rent_amount'])
            ->orderBy('code');

        if ($blockId = $request->integer('block_id')) {
            $query->where('block_id', $blockId);
        }

        if ($status = trim((string) $request->string('status'))) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StorePlaceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $place = Place::query()->create($data);

        return response()->json([
            'message' => 'Place créée avec succès.',
            'data' => $place->load('block:id,code,name,default_rent_amount'),
        ], 201);
    }

    public function show(Place $place): JsonResponse
    {
        return response()->json([
            'data' => $place->load(['block:id,code,name,default_rent_amount', 'assignments.merchant']),
        ]);
    }

    public function update(UpdatePlaceRequest $request, Place $place): JsonResponse
    {
        $data = $request->validated();

        $place->update($data);

        return response()->json([
            'message' => 'Place mise à jour.',
            'data' => $place->fresh()->load('block:id,code,name,default_rent_amount'),
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
