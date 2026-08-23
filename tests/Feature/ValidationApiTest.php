<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Block;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Place;
use App\Models\PlaceAssignment;
use App\Models\RentObligation;
use App\Models\RentPeriod;

class ValidationApiTest extends ApiTestCase
{
    public function test_block_validation_errors_are_returned_on_create(): void
    {
        [, $token] = $this->makeUserWithToken('ADMIN', ['blocks.manage']);

        $this->postJson('/api/blocks', [
            'code' => '',
            'name' => '',
            'default_rent_amount' => -1,
        ], $this->authHeaders($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'default_rent_amount']);
    }

    public function test_place_validation_errors_are_returned_on_create(): void
    {
        [, $token] = $this->makeUserWithToken('ADMIN', ['places.manage']);

        $this->postJson('/api/places', [
            'status' => 'BROKEN',
        ], $this->authHeaders($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['block_id', 'code', 'status']);
    }

    public function test_merchant_validation_errors_are_returned_on_create(): void
    {
        [, $token] = $this->makeUserWithToken('ADMIN', ['merchants.manage']);

        $this->postJson('/api/merchants', [
            'merchant_code' => '',
        ], $this->authHeaders($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['merchant_code', 'business_name']);
    }

    public function test_bank_validation_errors_are_returned_on_create(): void
    {
        [, $token] = $this->makeUserWithToken('ACCOUNTANT', ['banks.manage']);

        $this->postJson('/api/banks', [
            'status' => 'DISABLED',
        ], $this->authHeaders($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'status']);
    }

    public function test_assignment_overlap_is_rejected(): void
    {
        [, $token] = $this->makeUserWithToken('ADMIN', ['assignments.manage', 'blocks.manage', 'places.manage', 'merchants.manage']);

        $block = Block::query()->create([
            'code' => 'VAL-BLK',
            'name' => 'Bloc Validation',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $place = Place::query()->create([
            'block_id' => $block->id,
            'code' => 'VAL-PL',
            'name' => 'Place Validation',
            'status' => 'AVAILABLE',
        ]);

        $merchant = Merchant::query()->create([
            'merchant_code' => 'VAL-MER',
            'business_name' => 'Marchand Validation',
            'status' => 'ACTIVE',
        ]);

        $this->postJson('/api/assignments', [
            'place_id' => $place->id,
            'merchant_id' => $merchant->id,
            'start_date' => '2026-08-01',
            'rent_amount' => 50000,
            'status' => 'ACTIVE',
        ], $this->authHeaders($token))
            ->assertCreated();

        $this->postJson('/api/assignments', [
            'place_id' => $place->id,
            'merchant_id' => $merchant->id,
            'start_date' => '2026-08-10',
            'rent_amount' => 50000,
            'status' => 'ACTIVE',
        ], $this->authHeaders($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['place_id']);
    }

    public function test_rent_period_validation_errors_are_returned_on_create(): void
    {
        [, $token] = $this->makeUserWithToken('ACCOUNTANT', ['rents.manage']);

        $this->postJson('/api/rent-periods', [
            'year' => 2026,
            'month' => 13,
            'period_start' => '2026-09-30',
            'period_end' => '2026-09-01',
            'due_date' => '2026-08-31',
            'status' => 'INVALID',
        ], $this->authHeaders($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['month', 'period_end', 'due_date', 'status']);
    }

    public function test_rent_obligation_validation_errors_are_returned_on_update(): void
    {
        [, $token] = $this->makeUserWithToken('ACCOUNTANT', ['rents.manage', 'blocks.manage', 'places.manage', 'assignments.manage', 'merchants.manage']);

        [, , $obligation] = $this->createGeneratedObligation($token);

        $this->patchJson("/api/rent-obligations/{$obligation->id}", [
            'status' => 'BROKEN',
        ], $this->authHeaders($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_payment_allocation_validation_errors_are_returned(): void
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

        [$merchant, $bank, $obligation] = $this->createGeneratedObligation($token);

        $this->postJson('/api/payments', [
            'merchant_id' => $merchant->id,
            'payment_date' => '2026-08-23',
            'amount' => 50000,
            'bank_id' => $bank->id,
            'reference_number' => 'VAL-PAY-1',
            'allocations' => [
                [
                    'rent_obligation_id' => $obligation->id,
                    'amount_allocated' => 20000,
                ],
            ],
        ], $this->authHeaders($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['allocations']);
    }

    public function test_receipt_cannot_be_cancelled_twice(): void
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

        $payment = $this->createPostedPaymentAndReceipt($token);

        $this->postJson("/api/receipts/{$payment->receipt->id}/cancel", [
            'reason' => 'Erreur',
        ], $this->authHeaders($token))
            ->assertOk();

        $this->postJson("/api/receipts/{$payment->receipt->id}/cancel", [
            'reason' => 'Encore',
        ], $this->authHeaders($token))
            ->assertUnprocessable();
    }

    /**
     * @return array{0: Merchant, 1: Bank, 2: RentObligation}
     */
    private function createGeneratedObligation(string $token): array
    {
        $block = Block::query()->create([
            'code' => 'VAL-FIN-BLK',
            'name' => 'Bloc Finance Validation',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $place = Place::query()->create([
            'block_id' => $block->id,
            'code' => 'VAL-FIN-PL',
            'name' => 'Place Finance Validation',
            'status' => 'AVAILABLE',
        ]);

        $merchant = Merchant::query()->create([
            'merchant_code' => 'VAL-FIN-MER',
            'business_name' => 'Marchand Finance Validation',
            'status' => 'ACTIVE',
        ]);

        $bank = Bank::query()->create([
            'code' => 'VAL-FIN-BANK',
            'name' => 'Banque Finance Validation',
            'status' => 'ACTIVE',
        ]);

        PlaceAssignment::query()->create([
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

        $obligation = RentObligation::query()->where('rent_period_id', $period->id)->firstOrFail();

        return [$merchant, $bank, $obligation];
    }

    private function createPostedPaymentAndReceipt(string $token): Payment
    {
        $block = Block::query()->create([
            'code' => 'VAL-RCP-BLK',
            'name' => 'Bloc Reçu Validation',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $place = Place::query()->create([
            'block_id' => $block->id,
            'code' => 'VAL-RCP-PL',
            'name' => 'Place Reçu Validation',
            'status' => 'AVAILABLE',
        ]);

        $merchant = Merchant::query()->create([
            'merchant_code' => 'VAL-RCP-MER',
            'business_name' => 'Marchand Reçu Validation',
            'status' => 'ACTIVE',
        ]);

        $bank = Bank::query()->create([
            'code' => 'VAL-RCP-BANK',
            'name' => 'Banque Reçu Validation',
            'status' => 'ACTIVE',
        ]);

        PlaceAssignment::query()->create([
            'place_id' => $place->id,
            'merchant_id' => $merchant->id,
            'start_date' => '2026-08-01',
            'rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $period = RentPeriod::query()->create([
            'year' => 2026,
            'month' => 9,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'due_date' => '2026-09-10',
            'status' => 'OPEN',
        ]);

        $this->postJson("/api/rent-periods/{$period->id}/generate-obligations", [], $this->authHeaders($token))
            ->assertOk();

        $obligation = RentObligation::query()->where('rent_period_id', $period->id)->firstOrFail();

        $payment = $this->postJson('/api/payments', [
            'merchant_id' => $merchant->id,
            'payment_date' => '2026-09-23',
            'amount' => 50000,
            'bank_id' => $bank->id,
            'reference_number' => 'VAL-RCP-PAY',
            'auto_allocate' => true,
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        return Payment::query()->with('receipt')->findOrFail($payment['id']);
    }
}
