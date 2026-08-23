<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlaceMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PlaceMovement::query()
            ->with(['place.block', 'merchant', 'assignment', 'previousMerchant', 'newMerchant', 'creator.role'])
            ->orderByDesc('created_at');

        if ($placeId = $request->integer('place_id')) {
            $query->where('place_id', $placeId);
        }

        if ($merchantId = $request->integer('merchant_id')) {
            $query->where('merchant_id', $merchantId);
        }

        if ($type = $request->string('movement_type')->trim()) {
            $query->where('movement_type', $type->toString());
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function show(PlaceMovement $movement): JsonResponse
    {
        return response()->json([
            'data' => $movement->load(['place.block', 'merchant', 'assignment', 'previousMerchant', 'newMerchant', 'creator.role']),
        ]);
    }
}
