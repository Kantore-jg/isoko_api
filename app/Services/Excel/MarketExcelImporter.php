<?php

namespace App\Services\Excel;

use App\Models\Bank;
use App\Models\Block;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Merchant;
use App\Models\Place;
use App\Models\PlaceAssignment;
use App\Models\RentPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Orchestre l'import de chaque feuille Excel vers les entités métier correspondantes.
 */
class MarketExcelImporter
{
    /**
     * Retourne les importeurs disponibles pour le scope donné.
     *
     * @return array<string, callable(array<int, string|null>, int, ImportBatch): void>
     */
    public function importersForScope(string $scope): array
    {
        $all = [
            'blocks'       => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importBlock($row, $rowNumber, $batch),
            'places'       => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importPlace($row, $rowNumber, $batch),
            'merchants'    => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importMerchant($row, $rowNumber, $batch),
            'banks'        => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importBank($row, $rowNumber, $batch),
            'assignments'  => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importAssignment($row, $rowNumber, $batch),
            'rent_periods' => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importRentPeriod($row, $rowNumber, $batch),
        ];

        return match ($scope) {
            'structure' => array_intersect_key($all, array_flip(['blocks', 'places', 'merchants', 'banks', 'assignments'])),
            'finance'   => array_intersect_key($all, array_flip(['banks', 'rent_periods'])),
            default     => $all,
        };
    }

    /**
     * Transforme les lignes brutes d'une feuille en tableau associatif headers => valeur.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<string, string|null>>
     */
    public function rowsFromSheet(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $headers = array_map(fn ($value) => Str::snake((string) $value), array_shift($rows));
        $formatted = [];

        foreach ($rows as $row) {
            $mapped = [];
            foreach ($headers as $index => $header) {
                $mapped[$header] = $row[$index] ?? null;
            }

            if (collect($mapped)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                continue;
            }

            $formatted[] = $mapped;
        }

        return $formatted;
    }

    /**
     * Importe toutes les lignes d'une feuille et retourne le résumé (total / success / failed).
     *
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{total_rows: int, successful_rows: int, failed_rows: int}
     */
    public function importSheetRows(ImportBatch $batch, string $sheetName, array $rows, callable $importer, int &$rowCounter): array
    {
        $summary = [
            'total_rows'      => 0,
            'successful_rows' => 0,
            'failed_rows'     => 0,
        ];

        foreach ($rows as $row) {
            $rowNumber = ++$rowCounter;
            $summary['total_rows']++;

            try {
                DB::transaction(function () use ($row, $rowNumber, $batch, $importer): void {
                    $importer($row, $rowNumber, $batch);
                });

                ImportRow::query()->create([
                    'import_id'     => $batch->id,
                    'row_number'    => $rowNumber,
                    'data'          => $row,
                    'status'        => 'VALID',
                    'error_message' => null,
                ]);

                $summary['successful_rows']++;
            } catch (Throwable $throwable) {
                ImportRow::query()->create([
                    'import_id'     => $batch->id,
                    'row_number'    => $rowNumber,
                    'data'          => $row,
                    'status'        => 'FAILED',
                    'error_message' => $throwable->getMessage(),
                ]);

                $summary['failed_rows']++;
            }
        }

        return $summary;
    }

    // ─── Importeurs par entité ────────────────────────────────────────────────

    /**
     * @param  array<string, string|null>  $row
     */
    private function importBlock(array $row, int $rowNumber, ImportBatch $batch): void
    {
        $data = $this->requireValue($row, ['code', 'name'], $rowNumber, 'blocks');

        Block::query()->updateOrCreate(
            ['code' => $data['code']],
            [
                'name'                => $data['name'],
                'description'         => $row['description'] ?: null,
                'default_rent_amount' => $this->nullableDecimal($row['default_rent_amount']),
                'status'              => $row['status'] ?: 'ACTIVE',
            ]
        );
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function importPlace(array $row, int $rowNumber, ImportBatch $batch): void
    {
        $data  = $this->requireValue($row, ['block_code', 'code'], $rowNumber, 'places');
        $block = Block::query()->where('code', $data['block_code'])->first();

        if (! $block) {
            throw ValidationException::withMessages([
                'block_code' => "Bloc introuvable pour la ligne {$rowNumber}.",
            ]);
        }

        Place::query()->updateOrCreate(
            ['code' => $data['code']],
            [
                'block_id'    => $block->id,
                'name'        => $row['name'] ?: null,
                'description' => $row['description'] ?: null,
                'surface'     => $this->nullableDecimal($row['surface']),
                'type'        => $row['type'] ?: 'STANDARD',
                'status'      => $row['status'] ?: 'AVAILABLE',
            ]
        );
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function importMerchant(array $row, int $rowNumber, ImportBatch $batch): void
    {
        $data = $this->requireValue($row, ['merchant_code', 'business_name'], $rowNumber, 'merchants');

        Merchant::query()->updateOrCreate(
            ['merchant_code' => $data['merchant_code']],
            [
                'business_name'       => $data['business_name'],
                'owner_name'          => $row['owner_name'] ?: null,
                'national_id'         => $row['national_id'] ?: null,
                'phone'               => $row['phone'] ?: null,
                'phone_secondary'     => $row['phone_secondary'] ?: null,
                'email'               => $row['email'] ?: null,
                'address'             => $row['address'] ?: null,
                'business_type'       => $row['business_type'] ?: null,
                'registration_number' => $row['registration_number'] ?: null,
                'tax_number'          => $row['tax_number'] ?: null,
                'status'              => $row['status'] ?: 'ACTIVE',
                'registration_date'   => $row['registration_date'] ?: null,
                'notes'               => $row['notes'] ?: null,
            ]
        );
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function importBank(array $row, int $rowNumber, ImportBatch $batch): void
    {
        $data = $this->requireValue($row, ['code', 'name'], $rowNumber, 'banks');

        Bank::query()->updateOrCreate(
            ['code' => $data['code']],
            [
                'name'           => $data['name'],
                'account_name'   => $row['account_name'] ?: null,
                'account_number' => $row['account_number'] ?: null,
                'branch'         => $row['branch'] ?: null,
                'description'    => $row['description'] ?: null,
                'status'         => $row['status'] ?: 'ACTIVE',
            ]
        );
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function importAssignment(array $row, int $rowNumber, ImportBatch $batch): void
    {
        $data     = $this->requireValue($row, ['place_code', 'merchant_code', 'start_date', 'rent_amount'], $rowNumber, 'assignments');
        $place    = Place::query()->where('code', $data['place_code'])->first();
        $merchant = Merchant::query()->where('merchant_code', $data['merchant_code'])->first();

        if (! $place || ! $merchant) {
            throw ValidationException::withMessages([
                'assignment' => "Place ou commerçant introuvable pour la ligne {$rowNumber}.",
            ]);
        }

        PlaceAssignment::query()->updateOrCreate(
            [
                'place_id'    => $place->id,
                'merchant_id' => $merchant->id,
                'start_date'  => $data['start_date'],
            ],
            [
                'end_date'          => $row['end_date'] ?: null,
                'rent_amount'       => $data['rent_amount'],
                'status'            => $row['status'] ?: 'ACTIVE',
                'assignment_reason' => $row['assignment_reason'] ?: null,
                'notes'             => $row['notes'] ?: null,
            ]
        );
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function importRentPeriod(array $row, int $rowNumber, ImportBatch $batch): void
    {
        $data = $this->requireValue($row, ['year', 'month', 'period_start', 'period_end', 'due_date'], $rowNumber, 'rent_periods');

        RentPeriod::query()->updateOrCreate(
            ['year' => (int) $data['year'], 'month' => (int) $data['month']],
            [
                'period_start' => $data['period_start'],
                'period_end'   => $data['period_end'],
                'due_date'     => $data['due_date'],
                'status'       => $row['status'] ?: 'OPEN',
                'closed_at'    => $row['closed_at'] ?: null,
            ]
        );
    }

    // ─── Utilitaires ──────────────────────────────────────────────────────────

    /**
     * @param  array<string, string|null>  $row
     * @param  array<int, string>  $required
     * @return array<string, string>
     */
    private function requireValue(array $row, array $required, int $rowNumber, string $sheetName): array
    {
        $values = [];

        foreach ($required as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value === '') {
                throw ValidationException::withMessages([
                    $field => ucfirst($field).' est obligatoire pour la ligne '.$rowNumber.' ('.$sheetName.').',
                ]);
            }

            $values[$field] = $value;
        }

        return $values;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : number_format((float) $string, 2, '.', '');
    }
}
