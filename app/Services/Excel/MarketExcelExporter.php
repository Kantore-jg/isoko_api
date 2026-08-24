<?php

namespace App\Services\Excel;

use App\Models\Bank;
use App\Models\Block;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Place;
use App\Models\PlaceAssignment;
use App\Models\Receipt;
use App\Models\RentObligation;
use App\Models\RentPeriod;

/**
 * Construit les tableaux de données à exporter pour chaque entité métier.
 * Chaque méthode retourne un tableau de lignes (headers + données).
 */
class MarketExcelExporter
{
    /**
     * @return array<int, array<int, mixed>>
     */
    public function exportBlocks(): array
    {
        $rows = [['code', 'name', 'description', 'default_rent_amount', 'status']];
        foreach (Block::query()->orderBy('code')->get() as $block) {
            $rows[] = [
                $block->code,
                $block->name,
                $block->description,
                $block->default_rent_amount,
                $block->status,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function exportPlaces(): array
    {
        $rows = [['block_code', 'code', 'name', 'description', 'surface', 'type', 'status']];
        foreach (Place::query()->with('block')->orderBy('code')->get() as $place) {
            $rows[] = [
                $place->block?->code,
                $place->code,
                $place->name,
                $place->description,
                $place->surface,
                $place->type,
                $place->status,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function exportMerchants(): array
    {
        $rows = [[
            'merchant_code', 'business_name', 'owner_name', 'national_id', 'phone', 'phone_secondary',
            'email', 'address', 'business_type', 'registration_number', 'tax_number', 'status', 'registration_date', 'notes',
        ]];

        foreach (Merchant::query()->orderBy('merchant_code')->get() as $merchant) {
            $rows[] = [
                $merchant->merchant_code,
                $merchant->business_name,
                $merchant->owner_name,
                $merchant->national_id,
                $merchant->phone,
                $merchant->phone_secondary,
                $merchant->email,
                $merchant->address,
                $merchant->business_type,
                $merchant->registration_number,
                $merchant->tax_number,
                $merchant->status,
                optional($merchant->registration_date)->toDateString(),
                $merchant->notes,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function exportBanks(): array
    {
        $rows = [['code', 'name', 'account_name', 'account_number', 'branch', 'description', 'status']];
        foreach (Bank::query()->orderBy('code')->get() as $bank) {
            $rows[] = [
                $bank->code,
                $bank->name,
                $bank->account_name,
                $bank->account_number,
                $bank->branch,
                $bank->description,
                $bank->status,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function exportAssignments(): array
    {
        $rows = [['place_code', 'merchant_code', 'start_date', 'end_date', 'rent_amount', 'status', 'assignment_reason', 'notes']];
        foreach (PlaceAssignment::query()->with(['place', 'merchant'])->orderBy('id')->get() as $assignment) {
            $rows[] = [
                $assignment->place?->code,
                $assignment->merchant?->merchant_code,
                optional($assignment->start_date)->toDateString(),
                optional($assignment->end_date)->toDateString(),
                $assignment->rent_amount,
                $assignment->status,
                $assignment->assignment_reason,
                $assignment->notes,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function exportRentPeriods(): array
    {
        $rows = [['year', 'month', 'period_start', 'period_end', 'due_date', 'status', 'closed_at']];
        foreach (RentPeriod::query()->orderBy('year')->orderBy('month')->get() as $period) {
            $rows[] = [
                $period->year,
                $period->month,
                optional($period->period_start)->toDateString(),
                optional($period->period_end)->toDateString(),
                optional($period->due_date)->toDateString(),
                $period->status,
                optional($period->closed_at)->toDateTimeString(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function exportRentObligations(): array
    {
        $rows = [['rent_period', 'place_code', 'merchant_code', 'amount_expected', 'amount_paid', 'balance', 'status', 'due_date', 'paid_at']];
        foreach (RentObligation::query()->with(['period', 'place', 'merchant'])->orderBy('id')->get() as $obligation) {
            $rows[] = [
                $obligation->period ? sprintf('%d-%02d', $obligation->period->year, $obligation->period->month) : null,
                $obligation->place?->code,
                $obligation->merchant?->merchant_code,
                $obligation->amount_expected,
                $obligation->amount_paid,
                $obligation->balance,
                $obligation->status,
                optional($obligation->due_date)->toDateString(),
                optional($obligation->paid_at)->toDateTimeString(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function exportPayments(): array
    {
        $rows = [['payment_number', 'merchant_code', 'bank_code', 'payment_date', 'amount', 'reference_number', 'payment_method', 'status', 'notes', 'received_by', 'posted_at', 'voided_at', 'void_reason']];
        foreach (Payment::query()->with(['merchant', 'bank', 'receiver'])->orderBy('payment_date')->get() as $payment) {
            $rows[] = [
                $payment->payment_number,
                $payment->merchant?->merchant_code,
                $payment->bank?->code,
                optional($payment->payment_date)->toDateString(),
                $payment->amount,
                $payment->reference_number,
                $payment->payment_method,
                $payment->status,
                $payment->notes,
                $payment->receiver?->username,
                optional($payment->posted_at)->toDateTimeString(),
                optional($payment->voided_at)->toDateTimeString(),
                $payment->void_reason,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function exportReceipts(): array
    {
        $rows = [['receipt_number', 'payment_reference_number', 'receipt_date', 'issued_by', 'status', 'document_path']];
        foreach (Receipt::query()->with(['payment', 'issuer'])->orderBy('receipt_date')->get() as $receipt) {
            $rows[] = [
                $receipt->receipt_number,
                $receipt->payment?->reference_number,
                optional($receipt->receipt_date)->toDateString(),
                $receipt->issuer?->username,
                $receipt->status,
                $receipt->document_path,
            ];
        }

        return $rows;
    }

    /**
     * Construit la liste des feuilles à exporter selon le scope.
     *
     * @return array<string, array<int, array<int, mixed>>>
     */
    public function sheetsForScope(string $scope): array
    {
        $all = [
            'blocks'           => $this->exportBlocks(),
            'places'           => $this->exportPlaces(),
            'merchants'        => $this->exportMerchants(),
            'banks'            => $this->exportBanks(),
            'assignments'      => $this->exportAssignments(),
            'rent_periods'     => $this->exportRentPeriods(),
            'rent_obligations' => $this->exportRentObligations(),
            'payments'         => $this->exportPayments(),
            'receipts'         => $this->exportReceipts(),
        ];

        return match ($scope) {
            'structure' => array_intersect_key($all, array_flip(['blocks', 'places'])),
            'finance'   => array_intersect_key($all, array_flip(['banks', 'rent_periods', 'rent_obligations', 'payments', 'receipts'])),
            default     => $all,
        };
    }

    /**
     * Construit la liste des feuilles de template selon le scope.
     *
     * @return array<string, array<int, array<int, mixed>>>
     */
    public function templateSheetsForScope(string $scope): array
    {
        $all = [
            'blocks'       => $this->templateBlocks(),
            'places'       => $this->templatePlaces(),
            'merchants'    => $this->templateMerchants(),
            'banks'        => $this->templateBanks(),
            'assignments'  => $this->templateAssignments(),
            'rent_periods' => $this->templateRentPeriods(),
            'instructions' => $this->templateInstructions(),
        ];

        return match ($scope) {
            'structure' => array_intersect_key($all, array_flip(['blocks', 'places', 'merchants', 'banks', 'assignments', 'instructions'])),
            'finance'   => array_intersect_key($all, array_flip(['banks', 'rent_periods', 'instructions'])),
            default     => $all,
        };
    }

    // ─── Templates ────────────────────────────────────────────────────────────

    /** @return array<int, array<int, mixed>> */
    private function templateBlocks(): array
    {
        return [
            ['code', 'name', 'description', 'default_rent_amount', 'status'],
            ['BLK-001', 'Bloc Central', 'Exemple', 50000, 'ACTIVE'],
        ];
    }

    /** @return array<int, array<int, mixed>> */
    private function templatePlaces(): array
    {
        return [
            ['block_code', 'code', 'name', 'description', 'surface', 'type', 'status'],
            ['BLK-001', 'PL-001', 'Place 1', 'Exemple', 12.5, 'STANDARD', 'AVAILABLE'],
        ];
    }

    /** @return array<int, array<int, mixed>> */
    private function templateMerchants(): array
    {
        return [
            ['merchant_code', 'business_name', 'owner_name', 'national_id', 'phone', 'phone_secondary', 'email', 'address', 'business_type', 'registration_number', 'tax_number', 'status', 'registration_date', 'notes'],
            ['MER-001', 'Boutique Exemple', 'Jean', null, '+25770000000', null, 'merchant@example.test', null, 'Retail', null, null, 'ACTIVE', '2026-08-23', ''],
        ];
    }

    /** @return array<int, array<int, mixed>> */
    private function templateBanks(): array
    {
        return [
            ['code', 'name', 'account_name', 'account_number', 'branch', 'description', 'status'],
            ['BANK-001', 'Banque Exemple', null, null, null, null, 'ACTIVE'],
        ];
    }

    /** @return array<int, array<int, mixed>> */
    private function templateAssignments(): array
    {
        return [
            ['place_code', 'merchant_code', 'start_date', 'end_date', 'rent_amount', 'status', 'assignment_reason', 'notes'],
            ['PL-001', 'MER-001', '2026-08-01', null, 50000, 'ACTIVE', 'Affectation initiale', null],
        ];
    }

    /** @return array<int, array<int, mixed>> */
    private function templateRentPeriods(): array
    {
        return [
            ['year', 'month', 'period_start', 'period_end', 'due_date', 'status', 'closed_at'],
            [2026, 8, '2026-08-01', '2026-08-31', '2026-08-10', 'OPEN', null],
        ];
    }

    /** @return array<int, array<int, mixed>> */
    private function templateInstructions(): array
    {
        return [
            ['sheet', 'usage'],
            ['blocks', 'Créer les blocs avant les places.'],
            ['places', 'Renseigner block_code avec le code du bloc.'],
            ['merchants', 'Créer les commerçants avant les affectations.'],
            ['assignments', 'Une affectation active relie un commerçant à une place.'],
            ['rent_periods', 'Les périodes servent à générer les obligations.'],
        ];
    }
}
