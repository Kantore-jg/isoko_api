<?php

namespace App\Services;

use App\Models\RentObligation;

class PaymentAllocationService
{
    /**
     * Build allocations by consuming unpaid obligations from oldest to newest.
     *
     * @return array{allocations: array<int, array{rent_obligation_id:int, amount_allocated:float}>, remaining: float, total_outstanding: float}
     */
    public function buildForMerchant(int $merchantId, float $amount, ?string $asOfDate = null): array
    {
        $query = RentObligation::query()
            ->with('period')
            ->where('merchant_id', $merchantId)
            ->whereIn('status', ['PENDING', 'PARTIAL', 'OVERDUE'])
            ->where('balance', '>', 0)
            ->orderBy('due_date')
            ->orderBy('id');

        if ($asOfDate) {
            $query->whereDate('due_date', '<=', $asOfDate);
        }

        $outstanding = $query->get();
        $remaining = round($amount, 2);
        $allocations = [];
        $totalOutstanding = round((float) $outstanding->sum('balance'), 2);

        foreach ($outstanding as $obligation) {
            if ($remaining <= 0) {
                break;
            }

            $balance = round((float) $obligation->balance, 2);
            $allocated = min($balance, $remaining);

            if ($allocated <= 0) {
                continue;
            }

            $allocations[] = [
                'rent_obligation_id' => $obligation->id,
                'amount_allocated' => round($allocated, 2),
                'period' => $obligation->period?->only(['id', 'year', 'month']),
            ];

            $remaining = round($remaining - $allocated, 2);
        }

        return [
            'allocations' => $allocations,
            'remaining' => $remaining,
            'total_outstanding' => $totalOutstanding,
        ];
    }
}
