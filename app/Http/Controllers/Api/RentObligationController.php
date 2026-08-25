<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RentObligation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RentObligationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RentObligation::query()
            ->with(['period', 'assignment.place.block', 'merchant'])
            ->orderByDesc('id');

        if ($status = trim((string) $request->string('status'))) {
            $query->where('status', $status);
        }

        if ($periodId = $request->integer('rent_period_id')) {
            $query->where('rent_period_id', $periodId);
        }

        if ($merchantId = $request->integer('merchant_id')) {
            $query->where('merchant_id', $merchantId);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function show(RentObligation $rentObligation): JsonResponse
    {
        return response()->json([
            'data' => $rentObligation->load(['period', 'assignment.place.block', 'merchant', 'allocations.payment']),
        ]);
    }

    public function update(Request $request, RentObligation $rentObligation): JsonResponse
    {
        $data = $request->validate([
            'amount_expected' => ['sometimes', 'numeric', 'min:0'],
            'amount_paid' => ['sometimes', 'numeric', 'min:0'],
            'balance' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['PENDING', 'PARTIAL', 'PAID', 'OVERDUE', 'CANCELLED'])],
            'due_date' => ['sometimes', 'date'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $rentObligation->update($data);

        return response()->json([
            'message' => 'Loyer mis à jour.',
            'data' => $rentObligation->fresh()->load(['period', 'assignment.place.block', 'merchant']),
        ]);
    }
}
