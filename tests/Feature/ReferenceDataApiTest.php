<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Block;
use App\Models\Merchant;
use App\Models\Place;
use App\Models\PlaceAssignment;
use App\Models\RentObligation;
use App\Models\RentPeriod;

class ReferenceDataApiTest extends ApiTestCase
{
    public function test_merchants_support_crud_operations(): void
    {
        [, $token] = $this->makeUserWithToken('ADMIN', ['merchants.manage']);

        $created = $this->postJson('/api/merchants', [
            'merchant_code' => 'MER-100',
            'business_name' => 'Boutique Centrale',
            'owner_name' => 'Aline',
            'status' => 'ACTIVE',
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        $this->getJson("/api/merchants/{$created['id']}", $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.business_name', 'Boutique Centrale');

        $this->assertDatabaseHas('merchants', [
            'id' => $created['id'],
            'merchant_code' => 'MER-100',
        ]);

        $this->patchJson("/api/merchants/{$created['id']}", [
            'business_name' => 'Boutique Centrale Mise à Jour',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.business_name', 'Boutique Centrale Mise à Jour');

        $this->deleteJson("/api/merchants/{$created['id']}", [], $this->authHeaders($token))
            ->assertOk();
    }

    public function test_merchant_cannot_be_deleted_when_it_is_still_assigned(): void
    {
        [, $token] = $this->makeUserWithToken('ADMIN', ['merchants.manage', 'assignments.manage', 'blocks.manage', 'places.manage']);

        $block = Block::query()->create([
            'code' => 'BLK-MER',
            'name' => 'Bloc Marchand',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $place = Place::query()->create([
            'block_id' => $block->id,
            'code' => 'PL-MER',
            'name' => 'Place Marchand',
            'status' => 'AVAILABLE',
        ]);

        $merchant = Merchant::query()->create([
            'merchant_code' => 'MER-LOCK',
            'business_name' => 'Marchand Bloqué',
            'status' => 'ACTIVE',
        ]);

        PlaceAssignment::query()->create([
            'place_id' => $place->id,
            'merchant_id' => $merchant->id,
            'start_date' => '2026-08-01',
            'rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $this->deleteJson("/api/merchants/{$merchant->id}", [], $this->authHeaders($token))
            ->assertUnprocessable();
    }

    public function test_banks_support_crud_operations(): void
    {
        [, $token] = $this->makeUserWithToken('ACCOUNTANT', ['banks.manage']);

        $created = $this->postJson('/api/banks', [
            'code' => 'BANK-1',
            'name' => 'Banque Centrale',
            'status' => 'ACTIVE',
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        $this->patchJson("/api/banks/{$created['id']}", [
            'name' => 'Banque Centrale Mise à Jour',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Banque Centrale Mise à Jour');

        $this->assertDatabaseHas('banks', [
            'id' => $created['id'],
            'code' => 'BANK-1',
        ]);

        $this->deleteJson("/api/banks/{$created['id']}", [], $this->authHeaders($token))
            ->assertOk();
    }

    public function test_bank_cannot_be_deleted_when_payments_exist(): void
    {
        [, $token] = $this->makeUserWithToken('ACCOUNTANT', [
            'banks.manage',
            'rents.manage',
            'payments.manage',
            'receipts.manage',
        ]);

        $block = Block::query()->create([
            'code' => 'BLK-BANK',
            'name' => 'Bloc Banque',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $place = Place::query()->create([
            'block_id' => $block->id,
            'code' => 'PL-BANK',
            'name' => 'Place Banque',
            'status' => 'AVAILABLE',
        ]);

        $merchant = Merchant::query()->create([
            'merchant_code' => 'MER-BANK',
            'business_name' => 'Marchand Banque',
            'status' => 'ACTIVE',
        ]);

        $bank = Bank::query()->create([
            'code' => 'BANK-USED',
            'name' => 'Banque Utilisée',
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
            ->assertOk()
            ->assertJsonPath('message', 'Obligations générées.');

        $this->assertDatabaseHas('rent_obligations', [
            'rent_period_id' => $period->id,
            'assignment_id' => $assignment->id,
        ]);

        $this->postJson('/api/payments', [
            'merchant_id' => $merchant->id,
            'payment_date' => '2026-08-23',
            'amount' => 50000,
            'bank_id' => $bank->id,
            'reference_number' => 'PAY-BANK-1',
            'auto_allocate' => true,
        ], $this->authHeaders($token))
            ->assertCreated();

        $this->deleteJson("/api/banks/{$bank->id}", [], $this->authHeaders($token))
            ->assertUnprocessable();
    }

    public function test_rent_periods_and_obligations_can_be_managed(): void
    {
        [, $token] = $this->makeUserWithToken('ACCOUNTANT', ['rents.manage', 'blocks.manage', 'places.manage', 'assignments.manage', 'merchants.manage']);

        $created = $this->postJson('/api/rent-periods', [
            'year' => 2026,
            'month' => 9,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'due_date' => '2026-09-10',
            'status' => 'OPEN',
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        $this->patchJson("/api/rent-periods/{$created['id']}", [
            'status' => 'CLOSED',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'CLOSED');

        $this->getJson("/api/rent-periods/{$created['id']}", $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'CLOSED');

        $this->deleteJson("/api/rent-periods/{$created['id']}", [], $this->authHeaders($token))
            ->assertOk();

        $block = Block::query()->create([
            'code' => 'BLK-OBL',
            'name' => 'Bloc Loyer',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $place = Place::query()->create([
            'block_id' => $block->id,
            'code' => 'PL-OBL',
            'name' => 'Place Loyer',
            'status' => 'AVAILABLE',
        ]);

        $merchant = Merchant::query()->create([
            'merchant_code' => 'MER-OBL',
            'business_name' => 'Marchand Loyer',
            'status' => 'ACTIVE',
        ]);

        $assignment = PlaceAssignment::query()->create([
            'place_id' => $place->id,
            'merchant_id' => $merchant->id,
            'start_date' => '2026-09-01',
            'rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $period = RentPeriod::query()->create([
            'year' => 2026,
            'month' => 10,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'due_date' => '2026-10-10',
            'status' => 'OPEN',
        ]);

        $this->postJson("/api/rent-periods/{$period->id}/generate-obligations", [], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('message', 'Obligations générées.');

        $obligation = RentObligation::query()->where('rent_period_id', $period->id)->firstOrFail();

        $this->assertDatabaseHas('rent_obligations', [
            'id' => $obligation->id,
            'rent_period_id' => $period->id,
        ]);

        $this->getJson("/api/rent-obligations/{$obligation->id}", $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.assignment_id', $assignment->id);

        $this->patchJson("/api/rent-obligations/{$obligation->id}", [
            'status' => 'PARTIAL',
            'amount_paid' => 10000,
            'balance' => 40000,
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'PARTIAL');
    }

    public function test_receipts_can_be_listed_shown_and_cancelled(): void
    {
        [, $token] = $this->makeUserWithToken('ACCOUNTANT', [
            'banks.manage',
            'rents.manage',
            'payments.manage',
            'receipts.manage',
            'blocks.manage',
            'places.manage',
            'assignments.manage',
            'merchants.manage',
        ]);

        [$merchant, $bank, $obligation] = $this->createPaidObligationForReceiptFlow($token);

        $payment = $this->postJson('/api/payments', [
            'merchant_id' => $merchant->id,
            'payment_date' => '2026-10-23',
            'amount' => 50000,
            'bank_id' => $bank->id,
            'reference_number' => 'RCPT-0001',
            'auto_allocate' => true,
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        $this->getJson('/api/receipts', $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.0.payment_id', $payment['id']);

        $receipt = $payment['receipt'];

        $this->getJson("/api/receipts/{$receipt['id']}", $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.payment.id', $payment['id']);

        $this->postJson("/api/receipts/{$receipt['id']}/cancel", [
            'reason' => 'Erreur de saisie',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertDatabaseHas('rent_obligations', [
            'id' => $obligation->id,
            'status' => 'PAID',
        ]);
    }

    /**
     * @return array{0: Merchant, 1: Bank, 2: RentObligation}
     */
    private function createPaidObligationForReceiptFlow(string $token): array
    {
        $block = Block::query()->create([
            'code' => 'BLK-RCPT',
            'name' => 'Bloc Reçu',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $place = Place::query()->create([
            'block_id' => $block->id,
            'code' => 'PL-RCPT',
            'name' => 'Place Reçu',
            'status' => 'AVAILABLE',
        ]);

        $merchant = Merchant::query()->create([
            'merchant_code' => 'MER-RCPT',
            'business_name' => 'Marchand Reçu',
            'status' => 'ACTIVE',
        ]);

        $bank = Bank::query()->create([
            'code' => 'BANK-RCPT',
            'name' => 'Banque Reçu',
            'status' => 'ACTIVE',
        ]);

        PlaceAssignment::query()->create([
            'place_id' => $place->id,
            'merchant_id' => $merchant->id,
            'start_date' => '2026-10-01',
            'rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $period = RentPeriod::query()->create([
            'year' => 2026,
            'month' => 10,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'due_date' => '2026-10-10',
            'status' => 'OPEN',
        ]);

        $this->postJson("/api/rent-periods/{$period->id}/generate-obligations", [], $this->authHeaders($token))
            ->assertOk();

        $obligation = RentObligation::query()->where('rent_period_id', $period->id)->firstOrFail();

        return [$merchant, $bank, $obligation];
    }
}
