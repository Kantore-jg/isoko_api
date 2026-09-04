<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlockRequest;
use App\Http\Requests\UpdateBlockRequest;
use App\Models\Block;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Block::query()
            ->select(['id', 'code', 'name', 'description', 'default_rent_amount', 'status', 'created_at', 'updated_at'])
            ->withCount('places')
            ->orderBy('code');

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreBlockRequest $request): JsonResponse
    {
        $data = $request->validated();

        $block = Block::query()->create($data);

        return response()->json([
            'message' => 'Bloc créé avec succès.',
            'data' => $block->loadCount('places'),
        ], 201);
    }

    public function show(Block $block): JsonResponse
    {
        return response()->json([
            'data' => $block->load(['places' => fn ($query) => $query->orderBy('code')])->loadCount('places'),
        ]);
    }

    public function update(UpdateBlockRequest $request, Block $block): JsonResponse
    {
        $data = $request->validated();

        $block->update($data);

        return response()->json([
            'message' => 'Bloc mis à jour.',
            'data' => $block->fresh()->loadCount('places'),
        ]);
    }

    public function destroy(Block $block): JsonResponse
    {
        if ($block->places()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un bloc qui contient encore des places.',
            ], 422);
        }

        $block->delete();

        return response()->json(['message' => 'Bloc supprimé.']);
    }
}
