<?php

namespace App\Services\Excel;

use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class ExcelWorkbookService
{
    /**
     * Create a temporary XLSX file for the provided sheets.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $sheets
     */
    public function createWorkbookFile(array $sheets, ?string $prefix = null): string
    {
        $path = tempnam(sys_get_temp_dir(), ($prefix ? Str::slug($prefix) : 'workbook').'-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary workbook.');
        }

        @unlink($path);
        $path .= '.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($sheets as $sheetName => $rows) {
            if (isset($previousSheet)) {
                $sheet = $writer->addNewSheetAndMakeItCurrent();
            } else {
                $sheet = $writer->getCurrentSheet();
            }
            $previousSheet = $sheet;

            $sheet->setName($this->sanitizeSheetName((string) $sheetName));

            foreach (array_values($rows) as $row) {
                $writer->addRow(Row::fromValues(array_map(
                    fn ($value) => $this->normalizeValue($value),
                    array_values($row),
                )));
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * Read an XLSX workbook into a sheet => rows array.
     *
     * @return array<string, array<int, array<string, string|null>>>
     */
    public function readWorkbook(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        $workbook = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = [];

                    foreach ($row->toArray() as $value) {
                        $cells[] = ($value === null || $value === '') ? null : trim((string) $value);
                    }

                    if ($cells === [] || array_filter($cells, fn ($v) => $v !== null) === []) {
                        continue;
                    }

                    $rows[] = $cells;
                }

                $workbook[$sheet->getName()] = $rows;
            }
        } finally {
            $reader->close();
        }

        return $workbook;
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $name) ?? $name;

        return Str::limit(trim($name), 31, '');
    }

    private function normalizeValue(mixed $value): string|int|float|bool|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return (string) $value;
    }
}
