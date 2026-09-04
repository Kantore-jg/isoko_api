<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMerchantRequest;
use App\Http\Requests\UpdateMerchantRequest;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Merchant::query()
            ->select([
                'id',
                'merchant_code',
                'business_name',
                'owner_name',
                'national_id',
                'phone',
                'phone_secondary',
                'email',
                'address',
                'business_type',
                'status',
                'registration_date',
                'notes',
                'created_at',
                'updated_at',
            ])
            ->orderBy('business_name');

        if ($status = trim((string) $request->string('status'))) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('merchant_code', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreMerchantRequest $request): JsonResponse
    {
        $data = $request->validated();

        $merchant = Merchant::query()->create($data);

        return response()->json([
            'message' => 'Commerçant créé avec succès.',
            'data' => $merchant->load('assignments.place.block'),
        ], 201);
    }

    public function show(Merchant $merchant): JsonResponse
    {
        return response()->json([
            'data' => $merchant->load(['assignments.place.block', 'payments.bank']),
        ]);
    }

    public function update(UpdateMerchantRequest $request, Merchant $merchant): JsonResponse
    {
        $data = $request->validated();

        $merchant->update($data);

        return response()->json([
            'message' => 'Commerçant mis à jour.',
            'data' => $merchant->fresh()->load('assignments.place.block'),
        ]);
    }

    public function destroy(Merchant $merchant): JsonResponse
    {
        if ($merchant->assignments()->where('status', 'ACTIVE')->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un commerçant encore affecté.',
            ], 422);
        }

        $merchant->delete();

        return response()->json(['message' => 'Commerçant supprimé.']);
    }
}
