<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Block;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Place;
use App\Models\PlaceAssignment;
use App\Models\Receipt;
use App\Models\RentObligation;
use App\Models\RentPeriod;
use App\Services\Excel\ExcelWorkbookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SpreadsheetController extends Controller
{
    public function __construct(
        private readonly ExcelWorkbookService $workbookService
    ) {
    }

    public function export(Request $request)
    {
        $scope = $request->string('scope')->trim()->lower()->toString() ?: 'all';
        $sheets = $this->exportSheets($scope);

        $path = $this->workbookService->createWorkbookFile($sheets, 'market-export');
        $filename = sprintf('market-%s-%s.xlsx', $scope, now()->format('Ymd-His'));

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
            'scope' => ['nullable', 'in:all,structure,finance'],
        ]);

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $data['file'];
        $storedPath = $uploadedFile->storeAs(
            'imports',
            sprintf('%s-%s.xlsx', now()->format('YmdHis'), Str::random(8))
        );

        if ($storedPath === false) {
            throw new RuntimeException('Unable to store the uploaded workbook.');
        }

        $absolutePath = Storage::path($storedPath);
        $scope = $data['scope'] ?? 'all';

        $batch = ImportBatch::query()->create([
            'user_id' => $request->user()?->id,
            'import_type' => 'market_excel',
            'file_name' => $uploadedFile->getClientOriginalName(),
            'file_path' => $storedPath,
            'status' => 'PROCESSING',
            'started_at' => now(),
        ]);

        $sheetData = $this->workbookService->readWorkbook($absolutePath);

        $summary = [
            'total_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'sheets' => [],
        ];
        $rowCounter = 1;

        try {
            foreach ($this->importSheets($scope) as $sheetName => $importer) {
                if (! isset($sheetData[$sheetName]) || $sheetData[$sheetName] === []) {
                    continue;
                }

                $rows = $this->rowsFromSheet($sheetData[$sheetName]);
                $sheetSummary = $this->importSheetRows($batch, $sheetName, $rows, $importer, $rowCounter);

                $summary['total_rows'] += $sheetSummary['total_rows'];
                $summary['successful_rows'] += $sheetSummary['successful_rows'];
                $summary['failed_rows'] += $sheetSummary['failed_rows'];
                $summary['sheets'][$sheetName] = $sheetSummary;
            }

            $batch->update([
                'total_rows' => $summary['total_rows'],
                'successful_rows' => $summary['successful_rows'],
                'failed_rows' => $summary['failed_rows'],
                'status' => $summary['failed_rows'] > 0 ? 'FAILED' : 'COMPLETED',
                'completed_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            $batch->update([
                'status' => 'FAILED',
                'completed_at' => now(),
            ]);

            throw $throwable;
        }

        return response()->json([
            'message' => 'Import Excel terminé.',
            'data' => [
                'batch' => $batch->fresh(),
                'summary' => $summary,
            ],
        ], 201);
    }

    /**
     * @return array<string, array<int, array<int, mixed>>>
     */
    private function exportSheets(string $scope): array
    {
        $allSheets = [
            'blocks' => $this->exportBlocks(),
            'places' => $this->exportPlaces(),
            'merchants' => $this->exportMerchants(),
            'banks' => $this->exportBanks(),
            'assignments' => $this->exportAssignments(),
            'rent_periods' => $this->exportRentPeriods(),
            'rent_obligations' => $this->exportRentObligations(),
            'payments' => $this->exportPayments(),
            'receipts' => $this->exportReceipts(),
        ];

        if ($scope === 'structure') {
            return array_intersect_key($allSheets, array_flip(['blocks', 'places']));
        }

        if ($scope === 'finance') {
            return array_intersect_key($allSheets, array_flip(['banks', 'rent_periods', 'rent_obligations', 'payments', 'receipts']));
        }

        return $allSheets;
    }

    /**
     * @return array<string, callable(array<int, string|null>, int, ImportBatch): void>
     */
    private function importSheets(string $scope): array
    {
        $all = [
            'blocks' => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importBlock($row, $rowNumber, $batch),
            'places' => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importPlace($row, $rowNumber, $batch),
            'merchants' => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importMerchant($row, $rowNumber, $batch),
            'banks' => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importBank($row, $rowNumber, $batch),
            'assignments' => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importAssignment($row, $rowNumber, $batch),
            'rent_periods' => fn (array $row, int $rowNumber, ImportBatch $batch) => $this->importRentPeriod($row, $rowNumber, $batch),
        ];

        return match ($scope) {
            'structure' => array_intersect_key($all, array_flip(['blocks', 'places', 'merchants', 'banks', 'assignments'])),
            'finance' => array_intersect_key($all, array_flip(['banks', 'rent_periods'])),
            default => $all,
        };
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<string, string|null>>
     */
    private function rowsFromSheet(array $rows): array
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
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function importSheetRows(ImportBatch $batch, string $sheetName, array $rows, callable $importer, int &$rowCounter): array
    {
        $summary = [
            'total_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = ++$rowCounter;
            $summary['total_rows']++;

            try {
                DB::transaction(function () use ($row, $rowNumber, $batch, $importer): void {
                    $importer($row, $rowNumber, $batch);
                });

                ImportRow::query()->create([
                    'import_id' => $batch->id,
                    'row_number' => $rowNumber,
                    'data' => $row,
                    'status' => 'VALID',
                    'error_message' => null,
                ]);

                $summary['successful_rows']++;
            } catch (Throwable $throwable) {
                ImportRow::query()->create([
                    'import_id' => $batch->id,
                    'row_number' => $rowNumber,
                    'data' => $row,
                    'status' => 'FAILED',
                    'error_message' => $throwable->getMessage(),
                ]);

                $summary['failed_rows']++;
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function importBlock(array $row, int $rowNumber, ImportBatch $batch): void
    {
        $data = $this->requireValue($row, ['code', 'name'], $rowNumber, 'blocks');

        Block::query()->updateOrCreate(
            ['code' => $data['code']],
            [
                'name' => $data['name'],
                'description' => $row['description'] ?: null,
                'default_rent_amount' => $this->nullableDecimal($row['default_rent_amount']),
                'status' => $row['status'] ?: 'ACTIVE',
            ]
        );
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function importPlace(array $row, int $rowNumber, ImportBatch $batch): void
    {
        $data = $this->requireValue($row, ['block_code', 'code'], $rowNumber, 'places');
        $block = Block::query()->where('code', $data['block_code'])->first();

        if (! $block) {
            throw ValidationException::withMessages([
                'block_code' => "Bloc introuvable pour la ligne {$rowNumber}.",
            ]);
        }

        Place::query()->updateOrCreate(
            ['code' => $data['code']],
            [
                'block_id' => $block->id,
                'name' => $row['name'] ?: null,
                'description' => $row['description'] ?: null,
                'surface' => $this->nullableDecimal($row['surface']),
                'type' => $row['type'] ?: 'STANDARD',
                'status' => $row['status'] ?: 'AVAILABLE',
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
                'business_name' => $data['business_name'],
                'owner_name' => $row['owner_name'] ?: null,
                'national_id' => $row['national_id'] ?: null,
                'phone' => $row['phone'] ?: null,
                'phone_secondary' => $row['phone_secondary'] ?: null,
                'email' => $row['email'] ?: null,
                'address' => $row['address'] ?: null,
                'business_type' => $row['business_type'] ?: null,
                'registration_number' => $row['registration_number'] ?: null,
                'tax_number' => $row['tax_number'] ?: null,
                'status' => $row['status'] ?: 'ACTIVE',
                'registration_date' => $row['registration_date'] ?: null,
                'notes' => $row['notes'] ?: null,
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
                'name' => $data['name'],
                'account_name' => $row['account_name'] ?: null,
                'account_number' => $row['account_number'] ?: null,
                'branch' => $row['branch'] ?: null,
                'description' => $row['description'] ?: null,
                'status' => $row['status'] ?: 'ACTIVE',
            ]
        );
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function importAssignment(array $row, int $rowNumber, ImportBatch $batch): void
    {
        $data = $this->requireValue($row, ['place_code', 'merchant_code', 'start_date', 'rent_amount'], $rowNumber, 'assignments');
        $place = Place::query()->where('code', $data['place_code'])->first();
        $merchant = Merchant::query()->where('merchant_code', $data['merchant_code'])->first();

        if (! $place || ! $merchant) {
            throw ValidationException::withMessages([
                'assignment' => "Place ou commerçant introuvable pour la ligne {$rowNumber}.",
            ]);
        }

        PlaceAssignment::query()->updateOrCreate(
            [
                'place_id' => $place->id,
                'merchant_id' => $merchant->id,
                'start_date' => $data['start_date'],
            ],
            [
                'end_date' => $row['end_date'] ?: null,
                'rent_amount' => $data['rent_amount'],
                'status' => $row['status'] ?: 'ACTIVE',
                'assignment_reason' => $row['assignment_reason'] ?: null,
                'notes' => $row['notes'] ?: null,
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
                'period_end' => $data['period_end'],
                'due_date' => $data['due_date'],
                'status' => $row['status'] ?: 'OPEN',
                'closed_at' => $row['closed_at'] ?: null,
            ]
        );
    }

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

    /**
     * @return array<int, array<int, mixed>>
     */
    private function exportBlocks(): array
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
    private function exportPlaces(): array
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
    private function exportMerchants(): array
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
    private function exportBanks(): array
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
    private function exportAssignments(): array
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
    private function exportRentPeriods(): array
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
    private function exportRentObligations(): array
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
    private function exportPayments(): array
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
    private function exportReceipts(): array
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
}
