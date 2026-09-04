<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\TerminateAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Models\AuditLog;
use App\Models\Place;
use App\Models\PlaceAssignment;
use App\Models\PlaceMovement;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PlaceAssignment::query()
            ->with(['place.block', 'merchant'])
            ->orderByDesc('start_date');

        if ($status = trim((string) $request->string('status'))) {
            $query->where('status', $status);
        }

        if ($placeId = $request->integer('place_id')) {
            $query->where('place_id', $placeId);
        }

        if ($merchantId = $request->integer('merchant_id')) {
            $query->where('merchant_id', $merchantId);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreAssignmentRequest $request): JsonResponse
    {
        $data = $request->validated();

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

    public function update(UpdateAssignmentRequest $request, PlaceAssignment $assignment): JsonResponse
    {
        $data = $request->validated();

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

    public function terminate(TerminateAssignmentRequest $request, PlaceAssignment $assignment): JsonResponse
    {
        $data = $request->validated();

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
