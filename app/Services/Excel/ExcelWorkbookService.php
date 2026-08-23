<?php

namespace App\Services\Excel;

use DOMDocument;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

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

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the XLSX archive.');
        }

        $sheetNames = array_keys($sheets);

        $zip->addFromString(
            '[Content_Types].xml',
            $this->contentTypesXml($sheetNames)
        );
        $zip->addFromString(
            '_rels/.rels',
            $this->rootRelsXml()
        );
        $zip->addFromString(
            'xl/workbook.xml',
            $this->workbookXml($sheetNames)
        );
        $zip->addFromString(
            'xl/_rels/workbook.xml.rels',
            $this->workbookRelsXml($sheetNames)
        );
        $zip->addFromString(
            'xl/styles.xml',
            $this->stylesXml()
        );

        foreach (array_values($sheets) as $index => $rows) {
            $zip->addFromString(
                'xl/worksheets/sheet'.($index + 1).'.xml',
                $this->sheetXml($rows)
            );
        }

        $zip->close();

        return $path;
    }

    /**
     * Read an XLSX workbook into a sheet => rows array.
     *
     * @return array<string, array<int, array<string, string|null>>>
     */
    public function readWorkbook(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open the XLSX archive.');
        }

        $sheetMap = $this->sheetMap($zip);
        $sharedStrings = $this->sharedStrings($zip);

        $workbook = [];
        foreach ($sheetMap as $sheetName => $sheetPath) {
            $workbook[$sheetName] = $this->readSheet($zip->getFromName($sheetPath) ?: '', $sharedStrings);
        }

        $zip->close();

        return $workbook;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        foreach (array_values($rows) as $rowIndex => $row) {
            $xml .= '<row r="'.($rowIndex + 1).'">';
            $columnIndex = 1;
            foreach ($row as $value) {
                $cellRef = $this->columnLetter($columnIndex).($rowIndex + 1);
                $xml .= $this->cellXml($cellRef, $value);
                $columnIndex++;
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    /**
     * @param  mixed  $value
     */
    private function cellXml(string $reference, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '<c r="'.$reference.'" t="inlineStr"><is><t/></is></c>';
        }

        if (is_bool($value)) {
            return '<c r="'.$reference.'" t="b"><v>'.($value ? '1' : '0').'</v></c>';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'"><v>'.$this->escapeXml((string) $value).'</v></c>';
        }

        return '<c r="'.$reference.'" t="inlineStr"><is><t>'.$this->escapeXml((string) $value).'</t></is></c>';
    }

    /**
     * @param  array<int, string>  $sheetNames
     */
    private function workbookXml(array $sheetNames): string
    {
        $sheets = '';
        foreach ($sheetNames as $index => $sheetName) {
            $sheets .= '<sheet name="'.$this->escapeXml($this->sanitizeSheetName($sheetName)).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .'</workbook>';
    }

    /**
     * @param  array<int, string>  $sheetNames
     */
    private function workbookRelsXml(array $sheetNames): string
    {
        $rels = '';
        foreach ($sheetNames as $index => $_sheetName) {
            $rels .= '<Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }

        $rels .= '<Relationship Id="rId'.(count($sheetNames) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }

    /**
     * @param  array<int, string>  $sheetNames
     */
    private function contentTypesXml(array $sheetNames): string
    {
        $overrides = '';
        foreach ($sheetNames as $index => $_sheetName) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'</styleSheet>';
    }

    /**
     * @return array<string, string>
     */
    private function sheetMap(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml') ?: '';
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels') ?: '';

        $workbook = new DOMDocument();
        $workbook->loadXML($workbookXml);

        $rels = new DOMDocument();
        $rels->loadXML($relsXml);

        $sheetNodes = $workbook->getElementsByTagName('sheet');
        $relationshipNodes = $rels->getElementsByTagName('Relationship');

        $relationshipTargets = [];
        foreach ($relationshipNodes as $relationshipNode) {
            /** @var \DOMElement $relationshipNode */
            $relationshipTargets[$relationshipNode->getAttribute('Id')] = 'xl/_rels/'.ltrim($relationshipNode->getAttribute('Target'), '/');
        }

        $map = [];
        foreach ($sheetNodes as $sheetNode) {
            /** @var \DOMElement $sheetNode */
            $sheetName = $sheetNode->getAttribute('name');
            $rId = $sheetNode->getAttribute('r:id');
            $map[$sheetName] = str_replace('xl/_rels/', 'xl/', $relationshipTargets[$rId] ?? '');
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || $xml === '') {
            return [];
        }

        $document = new DOMDocument();
        $document->loadXML($xml);

        $strings = [];
        foreach ($document->getElementsByTagName('si') as $item) {
            $strings[] = trim($item->textContent);
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string|null>>
     */
    private function readSheet(string $xml, array $sharedStrings): array
    {
        if ($xml === '') {
            return [];
        }

        $document = new DOMDocument();
        $document->loadXML($xml);

        $rows = [];
        foreach ($document->getElementsByTagName('row') as $rowNode) {
            $cells = [];
            foreach ($rowNode->getElementsByTagName('c') as $cellNode) {
                /** @var \DOMElement $cellNode */
                $reference = $cellNode->getAttribute('r');
                $index = $this->columnIndex(preg_replace('/\d+/', '', $reference));
                $type = $cellNode->getAttribute('t');

                $value = null;
                if ($type === 'inlineStr') {
                    $value = trim($cellNode->textContent);
                } elseif ($type === 's') {
                    $sharedIndex = (int) trim($cellNode->textContent);
                    $value = $sharedStrings[$sharedIndex] ?? null;
                } elseif ($type === 'b') {
                    $value = trim($cellNode->textContent) === '1' ? '1' : '0';
                } else {
                    $value = trim($cellNode->textContent);
                }

                $cells[$index] = $value === '' ? null : $value;
            }

            if ($cells === []) {
                continue;
            }

            ksort($cells);
            $rows[] = array_values($cells);
        }

        return $rows;
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $name) ?? $name;

        return Str::limit(trim($name), 31, '');
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function columnLetter(int $index): string
    {
        $letter = '';

        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    private function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index;
    }
}
