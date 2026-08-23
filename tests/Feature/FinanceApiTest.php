<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Block;
use App\Models\Merchant;
use App\Models\Place;
use App\Models\PlaceAssignment;
use App\Models\RentPeriod;
use App\Models\User;

class FinanceApiTest extends ApiTestCase
{
    public function test_generate_obligations_and_auto_allocate_payment_across_months(): void
    {
        [, $token] = $this->makeUserWithToken('ACCOUNTANT', [
            'banks.manage',
            'rents.manage',
            'payments.manage',
            'receipts.manage',
            'reports.view',
            'dashboard.view',
        ]);

        $block = Block::query()->create([
            'code' => 'FIN-BLK',
            'name' => 'Bloc Finance',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $place = Place::query()->create([
            'block_id' => $block->id,
            'code' => 'FIN-PL-1',
            'name' => 'Place Finance',
            'status' => 'AVAILABLE',
        ]);

        $merchant = Merchant::query()->create([
            'merchant_code' => 'MER-FIN',
            'business_name' => 'Marchand Finance',
            'status' => 'ACTIVE',
        ]);

        $bank = Bank::query()->create([
            'code' => 'BK1',
            'name' => 'Banque Test',
            'status' => 'ACTIVE',
        ]);

        $assignment = PlaceAssignment::query()->create([
            'place_id' => $place->id,
            'merchant_id' => $merchant->id,
            'start_date' => '2026-07-01',
            'rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $july = RentPeriod::query()->create([
            'year' => 2026,
            'month' => 7,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'due_date' => '2026-07-10',
            'status' => 'OPEN',
        ]);

        $august = RentPeriod::query()->create([
            'year' => 2026,
            'month' => 8,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'due_date' => '2026-08-10',
            'status' => 'OPEN',
        ]);

        $this->postJson("/api/rent-periods/{$july->id}/generate-obligations", [], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('generated', 1);

        $this->postJson("/api/rent-periods/{$august->id}/generate-obligations", [], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('generated', 1);

        $preview = $this->postJson('/api/payments/preview-allocation', [
            'merchant_id' => $merchant->id,
            'amount' => 100000,
            'as_of_date' => '2026-08-31',
        ], $this->authHeaders($token))
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $preview['allocations']);

        $payment = $this->postJson('/api/payments', [
            'merchant_id' => $merchant->id,
            'payment_date' => '2026-08-23',
            'amount' => 100000,
            'bank_id' => $bank->id,
            'reference_number' => 'REC-2026-000500',
            'auto_allocate' => true,
            'notes' => 'Paiement multi-mois',
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        $this->assertDatabaseHas('payments', [
            'id' => $payment['id'],
            'status' => 'POSTED',
            'reference_number' => 'REC-2026-000500',
        ]);

        $this->assertDatabaseHas('receipts', [
            'payment_id' => $payment['id'],
            'receipt_number' => 'REC-2026-000500',
            'status' => 'VALID',
        ]);

        $this->assertDatabaseCount('payment_allocations', 2);

        $this->assertDatabaseHas('rent_obligations', [
            'assignment_id' => $assignment->id,
            'status' => 'PAID',
            'amount_paid' => '50000.00',
        ]);
    }

    public function test_payment_void_reverses_allocations_and_cancels_receipt(): void
    {
        [, $token] = $this->makeUserWithToken('ACCOUNTANT', [
            'banks.manage',
            'rents.manage',
            'payments.manage',
            'receipts.manage',
            'dashboard.view',
        ]);

        $block = Block::query()->create([
            'code' => 'VOID-BLK',
            'name' => 'Bloc Void',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $place = Place::query()->create([
            'block_id' => $block->id,
            'code' => 'VOID-PL',
            'name' => 'Place Void',
            'status' => 'AVAILABLE',
        ]);

        $merchant = Merchant::query()->create([
            'merchant_code' => 'MER-VOID',
            'business_name' => 'Marchand Void',
            'status' => 'ACTIVE',
        ]);

        $bank = Bank::query()->create([
            'code' => 'BK2',
            'name' => 'Banque Void',
            'status' => 'ACTIVE',
        ]);

        $assignment = PlaceAssignment::query()->create([
            'place_id' => $place->id,
            'merchant_id' => $merchant->id,
            'start_date' => '2026-08-01',
            'rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $period = RentPeriod::query()->create([
            'year' => 2026,
            'month' => 8,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'due_date' => '2026-08-10',
            'status' => 'OPEN',
        ]);

        $this->postJson("/api/rent-periods/{$period->id}/generate-obligations", [], $this->authHeaders($token))
            ->assertOk();

        $payment = $this->postJson('/api/payments', [
            'merchant_id' => $merchant->id,
            'payment_date' => '2026-08-23',
            'amount' => 50000,
            'bank_id' => $bank->id,
            'reference_number' => 'REC-2026-000501',
            'auto_allocate' => true,
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        $this->postJson("/api/payments/{$payment['id']}/void", [
            'void_reason' => 'Erreur de saisie',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'VOIDED');

        $this->assertDatabaseHas('receipts', [
            'payment_id' => $payment['id'],
            'status' => 'CANCELLED',
        ]);
    }
}
