<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Block;
use App\Models\Merchant;
use App\Models\Place;
use App\Models\PlaceAssignment;
use App\Models\RentPeriod;
use App\Services\Excel\ExcelWorkbookService;
use Illuminate\Http\UploadedFile;

class SpreadsheetApiTest extends ApiTestCase
{
    public function test_export_returns_a_valid_xlsx_workbook(): void
    {
        [, $token] = $this->makeUserWithToken('SUPER_ADMIN', ['exports.manage']);

        $block = Block::query()->create([
            'code' => 'EXP-BLK',
            'name' => 'Bloc Export',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        Place::query()->create([
            'block_id' => $block->id,
            'code' => 'EXP-PL-1',
            'name' => 'Place Export',
            'status' => 'AVAILABLE',
        ]);

        $response = $this->get('/api/exports/excel', $this->authHeaders($token));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = $response->baseResponse->getFile()->getPathname();
        $workbook = app(ExcelWorkbookService::class)->readWorkbook($path);

        $this->assertArrayHasKey('blocks', $workbook);
        $this->assertArrayHasKey('places', $workbook);
        $this->assertArrayHasKey('payments', $workbook);
        $this->assertSame('code', $workbook['blocks'][0][0] ?? null);
        $this->assertSame('EXP-BLK', $workbook['blocks'][1][0] ?? null);
    }

    public function test_template_returns_fillable_workbook(): void
    {
        [, $token] = $this->makeUserWithToken('SUPER_ADMIN', ['imports.manage']);

        $response = $this->get('/api/imports/template-excel', $this->authHeaders($token));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = $response->baseResponse->getFile()->getPathname();
        $workbook = app(ExcelWorkbookService::class)->readWorkbook($path);

        $this->assertArrayHasKey('blocks', $workbook);
        $this->assertArrayHasKey('instructions', $workbook);
        $this->assertSame('code', $workbook['blocks'][0][0] ?? null);
        $this->assertSame('sheet', $workbook['instructions'][0][0] ?? null);
    }

    public function test_import_creates_core_entities_from_an_xlsx_workbook(): void
    {
        [, $token] = $this->makeUserWithToken('SUPER_ADMIN', ['imports.manage']);

        $service = app(ExcelWorkbookService::class);
        $path = $service->createWorkbookFile([
            'blocks' => [
                ['code', 'name', 'description', 'default_rent_amount', 'status'],
                ['IMP-BLK', 'Bloc Import', 'Import test', 75000, 'ACTIVE'],
            ],
            'places' => [
                ['block_code', 'code', 'name', 'description', 'surface', 'type', 'status'],
                ['IMP-BLK', 'IMP-PL-1', 'Place Import', null, 10, 'STANDARD', 'AVAILABLE'],
            ],
            'merchants' => [
                ['merchant_code', 'business_name', 'owner_name', 'national_id', 'phone', 'phone_secondary', 'email', 'address', 'business_type', 'registration_number', 'tax_number', 'status', 'registration_date', 'notes'],
                ['IMP-MER', 'Commerce Import', 'Jean', null, '+25770000000', null, 'import@example.test', null, 'Retail', null, null, 'ACTIVE', '2026-08-23', 'Note'],
            ],
            'banks' => [
                ['code', 'name', 'account_name', 'account_number', 'branch', 'description', 'status'],
                ['IMP-BANK', 'Banque Import', null, null, null, null, 'ACTIVE'],
            ],
            'assignments' => [
                ['place_code', 'merchant_code', 'start_date', 'end_date', 'rent_amount', 'status', 'assignment_reason', 'notes'],
                ['IMP-PL-1', 'IMP-MER', '2026-08-01', null, 75000, 'ACTIVE', 'Import initial', null],
            ],
            'rent_periods' => [
                ['year', 'month', 'period_start', 'period_end', 'due_date', 'status', 'closed_at'],
                [2026, 8, '2026-08-01', '2026-08-31', '2026-08-10', 'OPEN', null],
            ],
        ], 'import-test');

        $uploadedFile = new UploadedFile(
            $path,
            'import-test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->post('/api/imports/excel', [
            'file' => $uploadedFile,
            'scope' => 'all',
        ], $this->authHeaders($token));

        $response->assertCreated()
            ->assertJsonPath('data.batch.status', 'COMPLETED');

        $this->assertDatabaseHas('blocks', ['code' => 'IMP-BLK']);
        $this->assertDatabaseHas('places', ['code' => 'IMP-PL-1']);
        $this->assertDatabaseHas('merchants', ['merchant_code' => 'IMP-MER']);
        $this->assertDatabaseHas('banks', ['code' => 'IMP-BANK']);
        $this->assertDatabaseHas('place_assignments', ['status' => 'ACTIVE']);
        $this->assertDatabaseHas('rent_periods', ['year' => 2026, 'month' => 8]);
    }
}
