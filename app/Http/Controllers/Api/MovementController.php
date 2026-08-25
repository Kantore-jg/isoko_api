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
            ->select([
                'id',
                'place_id',
                'merchant_id',
                'assignment_id',
                'previous_merchant_id',
                'new_merchant_id',
                'movement_type',
                'movement_date',
                'reason',
                'notes',
                'created_by',
                'created_at',
                'updated_at',
            ])
            ->with([
                'place:id,block_id,code',
                'place.block:id,code,name',
                'merchant:id,business_name,merchant_code',
                'assignment:id,place_id,merchant_id,status,start_date,end_date,rent_amount',
                'previousMerchant:id,business_name,merchant_code',
                'newMerchant:id,business_name,merchant_code',
                'creator:id,name,role_id',
                'creator.role:id,code,name',
            ])
            ->orderByDesc('created_at');

        if ($placeId = $request->integer('place_id')) {
            $query->where('place_id', $placeId);
        }

        if ($merchantId = $request->integer('merchant_id')) {
            $query->where('merchant_id', $merchantId);
        }

        if ($type = trim((string) $request->string('movement_type'))) {
            $query->where('movement_type', $type);
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
