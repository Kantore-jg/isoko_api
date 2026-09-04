<?php

namespace App\Services;

use App\Models\RentObligation;
use App\Models\RentPeriod;

class PaymentAllocationService
{
    /**
     * Build allocations by consuming unpaid obligations from oldest to newest.
     *
     * When $periodYear/$periodMonth are provided, the targeted period's obligation
     * is prioritised first, then any remaining amount is allocated FIFO to older debts.
     *
     * The $asOfDate is no longer used to filter obligations — an obligation exists
     * regardless of whether the payment date falls before or after the due date.
     * The due date only determines the payment timing (EARLY / ON_TIME / LATE).
     *
     * @return array{allocations: list<array{rent_obligation_id:int, amount_allocated:float, period:?array}>, remaining: float, total_outstanding: float, targeted_obligation: ?array, payment_timing: ?string}
     */
    public function buildForMerchant(
        int $merchantId,
        float $amount,
        ?string $asOfDate = null,
        ?int $periodYear = null,
        ?int $periodMonth = null
    ): array {
        $targeted = null;
        $paymentTiming = null;

        // Resolve the targeted obligation when a specific period is requested
        if ($periodYear && $periodMonth) {
            $period = RentPeriod::query()
                ->where('year', $periodYear)
                ->where('month', $periodMonth)
                ->first();

            if ($period) {
                $targeted = RentObligation::query()
                    ->with('period')
                    ->where('merchant_id', $merchantId)
                    ->where('rent_period_id', $period->id)
                    ->whereIn('status', ['PENDING', 'PARTIAL', 'OVERDUE'])
                    ->where('balance', '>', 0)
                    ->first();

                if ($targeted && $asOfDate) {
                    $paymentTiming = $this->computeTiming($asOfDate, $targeted->due_date);
                }
            }
        }

        // Build the full list of unpaid obligations (FIFO by due_date)
        $outstanding = RentObligation::query()
            ->with('period')
            ->where('merchant_id', $merchantId)
            ->whereIn('status', ['PENDING', 'PARTIAL', 'OVERDUE'])
            ->where('balance', '>', 0)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $totalOutstanding = round((float) $outstanding->sum('balance'), 2);
        $remaining = round($amount, 2);
        $allocations = [];

        // If a specific period was targeted, allocate to it first
        if ($targeted) {
            $balance = round((float) $targeted->balance, 2);
            $allocated = min($balance, $remaining);

            if ($allocated > 0) {
                $allocations[] = [
                    'rent_obligation_id' => $targeted->id,
                    'amount_allocated' => round($allocated, 2),
                    'period' => $targeted->period?->only(['id', 'year', 'month']),
                ];
                $remaining = round($remaining - $allocated, 2);
            }
        }

        // Allocate the rest FIFO across other unpaid obligations
        foreach ($outstanding as $obligation) {
            if ($remaining <= 0) {
                break;
            }

            // Skip the targeted obligation (already allocated above)
            if ($targeted && $obligation->id === $targeted->id) {
                continue;
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
            'targeted_obligation' => $targeted ? [
                'id' => $targeted->id,
                'period_year' => $targeted->period?->year,
                'period_month' => $targeted->period?->month,
                'period_label' => $targeted->period?->label ?? ($periodYear.'-'.str_pad((string) $periodMonth, 2, '0', STR_PAD_LEFT)),
                'amount_expected' => round((float) $targeted->amount_expected, 2),
                'amount_paid' => round((float) $targeted->amount_paid, 2),
                'balance' => round((float) $targeted->balance, 2),
                'due_date' => $targeted->due_date?->toDateString(),
                'status' => $targeted->status,
            ] : null,
            'payment_timing' => $paymentTiming,
        ];
    }

    /**
     * Determine the timing of a payment relative to the obligation due date.
     */
    private function computeTiming(string $paymentDate, $dueDate): string
    {
        $payment = strtotime($paymentDate);
        $due = strtotime($dueDate instanceof \DateTimeInterface ? $dueDate->format('Y-m-d') : (string) $dueDate);

        if ($payment < $due) {
            return 'EARLY';
        }

        if ($payment === $due) {
            return 'ON_TIME';
        }

        return 'LATE';
    }
}
