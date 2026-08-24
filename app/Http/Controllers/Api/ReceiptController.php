<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Receipt::query()
            ->select(['id', 'payment_id', 'receipt_number', 'receipt_date', 'issued_by', 'status', 'document_path', 'created_at', 'updated_at'])
            ->with([
                'payment:id,merchant_id,bank_id,payment_date,amount,reference_number,payment_method,status,received_by,posted_at',
                'payment.merchant:id,business_name,merchant_code',
                'payment.bank:id,code,name',
                'issuer:id,name,role_id',
            ])
            ->orderByDesc('receipt_date');
        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function show(Receipt $receipt): JsonResponse
    {
        return response()->json([
            'data' => $receipt->load(['payment.merchant', 'payment.bank', 'payment.allocations.obligation.period', 'issuer']),
        ]);
    }

    public function cancel(Request $request, Receipt $receipt): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($receipt->status === 'CANCELLED') {
            return response()->json([
                'message' => 'Ce reçu est déjà annulé.',
            ], 422);
        }

        $receipt->update(['status' => 'CANCELLED']);

        return response()->json([
            'message' => 'Reçu annulé.',
            'data' => $receipt->fresh()->load(['payment.merchant', 'payment.bank', 'issuer']),
            'reason' => $data['reason'] ?? null,
        ]);
    }
}
