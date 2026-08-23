<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Place;
use App\Models\PlaceAssignment;
use App\Models\PlaceMovement;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlaceAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PlaceAssignment::query()
            ->with(['place.block', 'merchant'])
            ->orderByDesc('start_date');

        if ($status = $request->string('status')->trim()) {
            $query->where('status', $status->toString());
        }

        if ($placeId = $request->integer('place_id')) {
            $query->where('place_id', $placeId);
        }

        if ($merchantId = $request->integer('merchant_id')) {
            $query->where('merchant_id', $merchantId);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'place_id' => ['required', 'exists:places,id'],
            'merchant_id' => ['required', 'exists:merchants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'rent_rate_id' => ['nullable', 'exists:rent_rates,id'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'ENDED', 'CANCELLED'])],
            'assignment_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->assertNoOverlap((int) $data['place_id'], $data['start_date'], $data['end_date'] ?? null);

        $user = $request->user();

        $assignment = DB::transaction(function () use ($data, $user) {
            $assignment = PlaceAssignment::query()->create([
                ...$data,
                'status' => $data['status'] ?? 'ACTIVE',
                'assigned_by' => $user?->id,
            ]);

            Place::query()->whereKey($assignment->place_id)->update(['status' => 'OCCUPIED']);
            Merchant::query()->whereKey($assignment->merchant_id)->update(['status' => 'ACTIVE']);

            PlaceMovement::query()->create([
                'place_id' => $assignment->place_id,
                'merchant_id' => $assignment->merchant_id,
                'assignment_id' => $assignment->id,
                'movement_type' => 'ENTRY',
                'movement_date' => $assignment->start_date,
                'new_merchant_id' => $assignment->merchant_id,
                'reason' => $assignment->assignment_reason ?? 'Affectation de la place',
                'notes' => $assignment->notes,
                'created_by' => $user?->id,
                'created_at' => now(),
            ]);

            if ($user) {
                AuditLog::query()->create([
                    'user_id' => $user->id,
                    'action' => 'ASSIGNMENT_CREATED',
                    'module' => 'occupancy',
                    'entity_type' => 'place_assignments',
                    'entity_id' => $assignment->id,
                    'new_values' => $assignment->toArray(),
                ]);
            }

            return $assignment;
        });

        return response()->json([
            'message' => 'Affectation créée avec succès.',
            'data' => $assignment->load(['place.block', 'merchant']),
        ], 201);
    }

    public function show(PlaceAssignment $assignment): JsonResponse
    {
        return response()->json([
            'data' => $assignment->load(['place.block', 'merchant', 'obligations.period']),
        ]);
    }

    public function update(Request $request, PlaceAssignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'place_id' => ['sometimes', 'exists:places,id'],
            'merchant_id' => ['sometimes', 'exists:merchants,id'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'rent_rate_id' => ['nullable', 'exists:rent_rates,id'],
            'rent_amount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'ENDED', 'CANCELLED'])],
            'assignment_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $placeId = (int) ($data['place_id'] ?? $assignment->place_id);
        $startDate = $data['start_date'] ?? $assignment->start_date->toDateString();
        $endDate = array_key_exists('end_date', $data)
            ? $data['end_date']
            : ($assignment->end_date?->toDateString());

        $this->assertNoOverlap($placeId, $startDate, $endDate, $assignment->id);

        $assignment->update($data);

        return response()->json([
            'message' => 'Affectation mise à jour.',
            'data' => $assignment->fresh()->load(['place.block', 'merchant']),
        ]);
    }

    public function terminate(Request $request, PlaceAssignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'end_date' => ['required', 'date', 'after_or_equal:' . $assignment->start_date->format('Y-m-d')],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($assignment, $data, $user): void {
            $assignment->update([
                'status' => 'ENDED',
                'end_date' => $data['end_date'],
                'ended_by' => $user?->id,
                'ended_at' => now(),
            ]);

            Place::query()->whereKey($assignment->place_id)->update(['status' => 'AVAILABLE']);

            PlaceMovement::query()->create([
                'place_id' => $assignment->place_id,
                'merchant_id' => $assignment->merchant_id,
                'assignment_id' => $assignment->id,
                'movement_type' => 'EXIT',
                'movement_date' => $data['end_date'],
                'previous_merchant_id' => $assignment->merchant_id,
                'reason' => $data['reason'] ?? 'Fin d\'affectation',
                'created_by' => $user?->id,
                'created_at' => now(),
            ]);

            if ($user) {
                AuditLog::query()->create([
                    'user_id' => $user->id,
                    'action' => 'ASSIGNMENT_TERMINATED',
                    'module' => 'occupancy',
                    'entity_type' => 'place_assignments',
                    'entity_id' => $assignment->id,
                    'new_values' => $assignment->fresh()->toArray(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Affectation terminée.',
            'data' => $assignment->fresh()->load(['place.block', 'merchant']),
        ]);
    }

    public function destroy(PlaceAssignment $assignment): JsonResponse
    {
        return response()->json([
            'message' => 'Les affectations ne sont jamais supprimées physiquement. Utilisez la terminaison ou l\'annulation.',
        ], 422);
    }

    private function assertNoOverlap(int $placeId, string $startDate, ?string $endDate, ?int $ignoreId = null): void
    {
        $newEnd = $endDate ?? '9999-12-31';

        $overlap = PlaceAssignment::query()
            ->where('place_id', $placeId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('status', '!=', 'CANCELLED')
            ->where(function ($query) use ($startDate, $newEnd): void {
                $query->where(function ($sub) use ($startDate, $newEnd): void {
                    $sub->whereDate('start_date', '<=', $newEnd)
                        ->where(function ($inner) use ($startDate): void {
                            $inner->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $startDate);
                        });
                });
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'place_id' => 'Chevauchement détecté pour cette place.',
            ]);
        }
    }
}
