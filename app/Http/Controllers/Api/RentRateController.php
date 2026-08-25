<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RentRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RentRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RentRate::query()
            ->with(['block:id,code,name', 'place:id,code,name'])
            ->orderByDesc('effective_from');

        if ($blockId = $request->integer('block_id')) {
            $query->where('block_id', $blockId);
        }

        if ($request->filled('place_id')) {
            $placeId = $request->input('place_id');
            // null = tarifs de bloc ; entier = tarifs de place précise
            if ($placeId === 'null' || $placeId === '0') {
                $query->whereNull('place_id');
            } else {
                $query->where('place_id', (int) $placeId);
            }
        }

        if ($status = trim((string) $request->string('status'))) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'block_id'       => ['required', 'exists:blocks,id'],
            'place_id'       => ['nullable', 'exists:places,id'],
            'amount'         => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to'   => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status'         => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);

        $data['created_by'] = $request->user()?->id;
        $data['status'] = $data['status'] ?? 'ACTIVE';

        $rentRate = RentRate::query()->create($data);

        return response()->json([
            'message' => 'Tarif de loyer créé.',
            'data'    => $rentRate->load(['block:id,code,name', 'place:id,code,name']),
        ], 201);
    }

    public function show(RentRate $rentRate): JsonResponse
    {
        return response()->json([
            'data' => $rentRate->load(['block:id,code,name', 'place:id,code,name']),
        ]);
    }

    public function update(Request $request, RentRate $rentRate): JsonResponse
    {
        $data = $request->validate([
            'block_id'       => ['sometimes', 'exists:blocks,id'],
            'place_id'       => ['nullable', 'exists:places,id'],
            'amount'         => ['sometimes', 'numeric', 'min:0'],
            'effective_from' => ['sometimes', 'date'],
            'effective_to'   => ['nullable', 'date'],
            'status'         => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);

        $rentRate->update($data);

        return response()->json([
            'message' => 'Tarif mis à jour.',
            'data'    => $rentRate->fresh()->load(['block:id,code,name', 'place:id,code,name']),
        ]);
    }

    public function destroy(RentRate $rentRate): JsonResponse
    {
        // Ne pas supprimer si des affectations actives y font référence
        if ($rentRate->assignments()->where('status', 'ACTIVE')->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un tarif lié à des affectations actives.',
            ], 422);
        }

        $rentRate->delete();

        return response()->json(['message' => 'Tarif supprimé.']);
    }
}
