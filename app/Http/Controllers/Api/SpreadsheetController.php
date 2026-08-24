<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\Excel\ExcelWorkbookService;
use App\Services\Excel\MarketExcelExporter;
use App\Services\Excel\MarketExcelImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SpreadsheetController extends Controller
{
    public function __construct(
        private readonly ExcelWorkbookService $workbookService,
        private readonly MarketExcelExporter  $exporter,
        private readonly MarketExcelImporter  $importer,
    ) {}

    public function export(Request $request)
    {
        $scope  = $request->string('scope')->trim()->lower()->toString() ?: 'all';
        $sheets = $this->exporter->sheetsForScope($scope);

        return $this->downloadWorkbook($sheets, sprintf('market-%s', $scope), 'export');
    }

    public function template(Request $request)
    {
        $scope  = $request->string('scope')->trim()->lower()->toString() ?: 'all';
        $sheets = $this->exporter->templateSheetsForScope($scope);

        return $this->downloadWorkbook($sheets, sprintf('market-template-%s', $scope), 'template');
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file'  => ['required', 'file', 'mimes:xlsx'],
            'scope' => ['nullable', 'in:all,structure,finance'],
        ]);

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $data['file'];
        $storedPath   = $uploadedFile->storeAs(
            'imports',
            sprintf('%s-%s.xlsx', now()->format('YmdHis'), Str::random(8))
        );

        if ($storedPath === false) {
            throw new RuntimeException('Unable to store the uploaded workbook.');
        }

        $absolutePath = Storage::path($storedPath);
        $scope        = $data['scope'] ?? 'all';

        $batch = ImportBatch::query()->create([
            'user_id'     => $request->user()?->id,
            'import_type' => 'market_excel',
            'file_name'   => $uploadedFile->getClientOriginalName(),
            'file_path'   => $storedPath,
            'status'      => 'PROCESSING',
            'started_at'  => now(),
        ]);

        $sheetData   = $this->workbookService->readWorkbook($absolutePath);
        $importers   = $this->importer->importersForScope($scope);
        $rowCounter  = 1;
        $summary     = ['total_rows' => 0, 'successful_rows' => 0, 'failed_rows' => 0, 'sheets' => []];

        try {
            foreach ($importers as $sheetName => $importer) {
                if (! isset($sheetData[$sheetName]) || $sheetData[$sheetName] === []) {
                    continue;
                }

                $rows         = $this->importer->rowsFromSheet($sheetData[$sheetName]);
                $sheetSummary = $this->importer->importSheetRows($batch, $sheetName, $rows, $importer, $rowCounter);

                $summary['total_rows']      += $sheetSummary['total_rows'];
                $summary['successful_rows'] += $sheetSummary['successful_rows'];
                $summary['failed_rows']     += $sheetSummary['failed_rows'];
                $summary['sheets'][$sheetName] = $sheetSummary;
            }

            $batch->update([
                'total_rows'      => $summary['total_rows'],
                'successful_rows' => $summary['successful_rows'],
                'failed_rows'     => $summary['failed_rows'],
                'status'          => $summary['failed_rows'] > 0 ? 'FAILED' : 'COMPLETED',
                'completed_at'    => now(),
            ]);
        } catch (Throwable $throwable) {
            $batch->update(['status' => 'FAILED', 'completed_at' => now()]);
            throw $throwable;
        }

        return response()->json([
            'message' => 'Import Excel terminé.',
            'data'    => ['batch' => $batch->fresh(), 'summary' => $summary],
        ], 201);
    }

    private function downloadWorkbook(array $sheets, string $prefix, string $kind)
    {
        $path     = $this->workbookService->createWorkbookFile($sheets, $prefix);
        $filename = sprintf('%s-%s.xlsx', $prefix, now()->format('Ymd-His'));

        return response()->download($path, $filename, [
            'Content-Type'    => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Workbook-Kind' => $kind,
        ])->deleteFileAfterSend(true);
    }
}
