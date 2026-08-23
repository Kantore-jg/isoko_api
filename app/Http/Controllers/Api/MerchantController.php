<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MerchantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Merchant::query()->with(['assignments.place.block'])->orderBy('business_name');

        if ($status = $request->string('status')->trim()) {
            $query->where('status', $status->toString());
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merchant_code' => ['required', 'string', 'max:50', 'unique:merchants,merchant_code'],
            'business_name' => ['required', 'string', 'max:200'],
            'owner_name' => ['nullable', 'string', 'max:200'],
            'national_id' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_secondary' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE', 'SUSPENDED', 'CLOSED'])],
            'registration_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

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

    public function update(Request $request, Merchant $merchant): JsonResponse
    {
        $data = $request->validate([
            'merchant_code' => ['sometimes', 'string', 'max:50', 'unique:merchants,merchant_code,' . $merchant->id],
            'business_name' => ['sometimes', 'string', 'max:200'],
            'owner_name' => ['nullable', 'string', 'max:200'],
            'national_id' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_secondary' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE', 'SUSPENDED', 'CLOSED'])],
            'registration_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

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
