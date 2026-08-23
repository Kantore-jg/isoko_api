<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use App\Models\RentObligation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with(['merchant', 'bank', 'receipt', 'allocations.obligation.period'])->orderByDesc('payment_date');

        if ($status = $request->string('status')->trim()) {
            $query->where('status', $status->toString());
        }

        if ($merchantId = $request->integer('merchant_id')) {
            $query->where('merchant_id', $merchantId);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merchant_id' => ['required', 'exists:merchants,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank_id' => ['required', 'exists:banks,id'],
            'reference_number' => ['required', 'string', 'max:100', 'unique:payments,reference_number'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'received_by' => ['nullable', 'exists:users,id'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.rent_obligation_id' => ['required', 'exists:rent_obligations,id'],
            'allocations.*.amount_allocated' => ['required', 'numeric', 'min:0.01'],
        ]);

        $allocationSum = round((float) collect($data['allocations'])->sum('amount_allocated'), 2);
        if ($allocationSum !== round((float) $data['amount'], 2)) {
            return response()->json([
                'message' => 'La somme des allocations doit être égale au montant du paiement.',
            ], 422);
        }

        $obligations = RentObligation::query()
            ->whereIn('id', collect($data['allocations'])->pluck('rent_obligation_id')->all())
            ->get()
            ->keyBy('id');

        foreach ($data['allocations'] as $allocationData) {
            $obligation = $obligations->get($allocationData['rent_obligation_id']);
            if (! $obligation) {
                throw ValidationException::withMessages([
                    'allocations' => 'Une obligation de loyer est introuvable.',
                ]);
            }

            if ((int) $obligation->merchant_id !== (int) $data['merchant_id']) {
                throw ValidationException::withMessages([
                    'allocations' => 'Chaque allocation doit appartenir au même commerçant que le paiement.',
                ]);
            }
        }

        $user = $request->user();

        $payment = DB::transaction(function () use ($data, $user, $obligations) {
            $payment = Payment::query()->create([
                'payment_number' => 'PAY-' . Str::upper(Str::random(10)),
                'merchant_id' => $data['merchant_id'],
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'bank_id' => $data['bank_id'],
                'reference_number' => $data['reference_number'],
                'payment_method' => $data['payment_method'] ?? null,
                'status' => 'POSTED',
                'notes' => $data['notes'] ?? null,
                'received_by' => $data['received_by'] ?? $user?->id,
                'posted_at' => now(),
            ]);

            foreach ($data['allocations'] as $allocationData) {
                $obligation = RentObligation::query()->lockForUpdate()->findOrFail($allocationData['rent_obligation_id']);

                PaymentAllocation::query()->create([
                    'payment_id' => $payment->id,
                    'rent_obligation_id' => $obligation->id,
                    'amount_allocated' => $allocationData['amount_allocated'],
                ]);

                $newPaid = (float) $obligation->amount_paid + (float) $allocationData['amount_allocated'];
                $newBalance = max(0, (float) $obligation->amount_expected - $newPaid);
                $obligation->update([
                    'amount_paid' => $newPaid,
                    'balance' => $newBalance,
                    'status' => $newBalance <= 0 ? 'PAID' : 'PARTIAL',
                    'paid_at' => now(),
                ]);
            }

            Receipt::query()->create([
                'payment_id' => $payment->id,
                'receipt_number' => $payment->reference_number,
                'receipt_date' => $payment->payment_date,
                'issued_by' => $payment->received_by,
                'status' => 'VALID',
            ]);

            if ($user) {
                AuditLog::query()->create([
                    'user_id' => $user->id,
                    'action' => 'PAYMENT_POSTED',
                    'module' => 'finance',
                    'entity_type' => 'payments',
                    'entity_id' => $payment->id,
                    'new_values' => $payment->toArray(),
                ]);
            }

            return $payment;
        });

        return response()->json([
            'message' => 'Paiement enregistré.',
            'data' => $payment->load(['merchant', 'bank', 'receipt', 'allocations.obligation.period']),
        ], 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json([
            'data' => $payment->load(['merchant', 'bank', 'receipt', 'allocations.obligation.period']),
        ]);
    }

    public function void(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ]);

        if ($payment->status === 'VOIDED') {
            return response()->json([
                'message' => 'Ce paiement est déjà annulé.',
            ], 422);
        }

        $user = $request->user();

        DB::transaction(function () use ($payment, $data, $user): void {
            foreach ($payment->allocations()->lockForUpdate()->get() as $allocation) {
                $obligation = $allocation->obligation()->lockForUpdate()->first();
                if ($obligation) {
                    $newPaid = max(0, (float) $obligation->amount_paid - (float) $allocation->amount_allocated);
                    $newBalance = max(0, (float) $obligation->amount_expected - $newPaid);
                    $obligation->update([
                        'amount_paid' => $newPaid,
                        'balance' => $newBalance,
                        'status' => $newBalance <= 0 ? 'PAID' : ($newPaid > 0 ? 'PARTIAL' : 'PENDING'),
                    ]);
                }
            }

            $payment->update([
                'status' => 'VOIDED',
                'voided_at' => now(),
                'void_reason' => $data['void_reason'],
            ]);

            if ($payment->receipt) {
                $payment->receipt->update(['status' => 'CANCELLED']);
            }

            if ($user) {
                AuditLog::query()->create([
                    'user_id' => $user->id,
                    'action' => 'PAYMENT_VOIDED',
                    'module' => 'finance',
                    'entity_type' => 'payments',
                    'entity_id' => $payment->id,
                    'new_values' => ['void_reason' => $data['void_reason']],
                ]);
            }
        });

        return response()->json([
            'message' => 'Paiement annulé.',
            'data' => $payment->fresh()->load(['merchant', 'bank', 'receipt', 'allocations.obligation.period']),
        ]);
    }
}
