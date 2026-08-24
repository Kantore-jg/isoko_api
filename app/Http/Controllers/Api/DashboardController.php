<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Place;
use App\Models\PlaceAssignment;
use App\Models\RentObligation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $payload = Cache::remember('dashboard.summary', now()->addSeconds(60), function (): array {
            $totalPlaces = Place::query()->count();
            $occupiedPlaces = Place::query()->where('status', 'OCCUPIED')->count();
            $availablePlaces = Place::query()->where('status', 'AVAILABLE')->count();
            $maintenancePlaces = Place::query()->where('status', 'MAINTENANCE')->count();
            $activeMerchants = Merchant::query()->where('status', 'ACTIVE')->count();
            $activeAssignments = PlaceAssignment::query()->where('status', 'ACTIVE')->count();
            $expectedMonthly = PlaceAssignment::query()
                ->where('status', 'ACTIVE')
                ->sum('rent_amount');
            $obtainedMonthly = Payment::query()
                ->where('status', 'POSTED')
                ->whereYear('payment_date', now()->year)
                ->whereMonth('payment_date', now()->month)
                ->sum('amount');
            $totalBanks = Bank::query()->where('status', 'ACTIVE')->count();
            $overdueObligations = RentObligation::query()
                ->whereIn('status', ['PENDING', 'PARTIAL', 'OVERDUE'])
                ->whereDate('due_date', '<', now()->toDateString())
                ->count();

            return [
                'market' => [
                    'name' => config('app.name'),
                    'timezone' => config('app.timezone'),
                ],
                'summary' => [
                    'total_places' => $totalPlaces,
                    'occupied_places' => $occupiedPlaces,
                    'available_places' => $availablePlaces,
                    'maintenance_places' => $maintenancePlaces,
                    'occupancy_rate' => $totalPlaces > 0 ? round(($occupiedPlaces / $totalPlaces) * 100, 2) : 0.0,
                'active_merchants' => $activeMerchants,
                'active_assignments' => $activeAssignments,
                'banks' => $totalBanks,
                'expected_monthly_revenue' => (float) $expectedMonthly,
                'obtained_monthly_revenue' => (float) $obtainedMonthly,
                    'overdue_obligations' => $overdueObligations,
                ],
            ];
        });

        return response()->json($payload);
    }
}
