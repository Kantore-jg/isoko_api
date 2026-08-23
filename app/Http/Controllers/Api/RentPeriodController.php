<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlaceAssignment;
use App\Models\RentObligation;
use App\Models\RentPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RentPeriodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RentPeriod::query()->orderByDesc('year')->orderByDesc('month');
        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000'],
            'month' => ['required', 'integer', 'between:1,12'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_date' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:period_end'],
            'status' => ['nullable', Rule::in(['OPEN', 'CLOSED'])],
        ]);

        $period = RentPeriod::query()->create($data);

        return response()->json(['message' => 'Période créée.', 'data' => $period], 201);
    }

    public function show(RentPeriod $rentPeriod): JsonResponse
    {
        return response()->json([
            'data' => $rentPeriod->load(['obligations.assignment.place.block', 'obligations.merchant']),
        ]);
    }

    public function update(Request $request, RentPeriod $rentPeriod): JsonResponse
    {
        $data = $request->validate([
            'year' => ['sometimes', 'integer', 'min:2000'],
            'month' => ['sometimes', 'integer', 'between:1,12'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(['OPEN', 'CLOSED'])],
            'closed_at' => ['nullable', 'date'],
        ]);

        $rentPeriod->update($data);

        return response()->json([
            'message' => 'Période mise à jour.',
            'data' => $rentPeriod->fresh(),
        ]);
    }

    public function destroy(RentPeriod $rentPeriod): JsonResponse
    {
        if ($rentPeriod->obligations()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une période déjà utilisée.',
            ], 422);
        }

        $rentPeriod->delete();

        return response()->json(['message' => 'Période supprimée.']);
    }

    public function generateObligations(Request $request, RentPeriod $rentPeriod): JsonResponse
    {
        $data = $request->validate([
            'force' => ['nullable', 'boolean'],
        ]);

        $generated = 0;
        $user = $request->user();

        DB::transaction(function () use ($rentPeriod, &$generated, $user, $data): void {
            $assignments = PlaceAssignment::query()
                ->where('status', '!=', 'CANCELLED')
                ->whereDate('start_date', '<=', $rentPeriod->period_end)
                ->where(function ($query) use ($rentPeriod): void {
                    $query->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $rentPeriod->period_start);
                })
                ->get();

            foreach ($assignments as $assignment) {
                $obligation = RentObligation::query()->firstOrCreate(
                    [
                        'rent_period_id' => $rentPeriod->id,
                        'assignment_id' => $assignment->id,
                    ],
                    [
                        'merchant_id' => $assignment->merchant_id,
                        'place_id' => $assignment->place_id,
                        'amount_expected' => $assignment->rent_amount,
                        'amount_paid' => 0,
                        'balance' => $assignment->rent_amount,
                        'status' => 'PENDING',
                        'due_date' => $rentPeriod->due_date,
                    ]
                );

                if ($obligation->wasRecentlyCreated) {
                    $generated++;
                } elseif (($data['force'] ?? false) === true) {
                    $obligation->update([
                        'merchant_id' => $assignment->merchant_id,
                        'place_id' => $assignment->place_id,
                        'amount_expected' => $assignment->rent_amount,
                        'balance' => max(0, $assignment->rent_amount - (float) $obligation->amount_paid),
                        'due_date' => $rentPeriod->due_date,
                    ]);
                }
            }

            if ($user) {
                AuditLog::query()->create([
                    'user_id' => $user->id,
                    'action' => 'RENT_OBLIGATIONS_GENERATED',
                    'module' => 'finance',
                    'entity_type' => 'rent_periods',
                    'entity_id' => $rentPeriod->id,
                    'new_values' => ['generated' => $generated],
                ]);
            }
        });

        return response()->json([
            'message' => 'Obligations générées.',
            'generated' => $generated,
        ]);
    }
}
