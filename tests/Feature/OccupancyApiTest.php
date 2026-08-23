<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Merchant;
use App\Models\Place;

class OccupancyApiTest extends ApiTestCase
{
    public function test_assignment_creation_and_termination_flow(): void
    {
        [, $token] = $this->makeUserWithToken('ADMIN', ['assignments.manage', 'merchants.manage', 'places.manage', 'blocks.manage']);

        $block = Block::query()->create([
            'code' => 'BLK-1',
            'name' => 'Bloc 1',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $place = Place::query()->create([
            'block_id' => $block->id,
            'code' => 'PL-1',
            'name' => 'Place 1',
            'status' => 'AVAILABLE',
        ]);

        $merchant = Merchant::query()->create([
            'merchant_code' => 'MER-1',
            'business_name' => 'Commerce Test',
            'status' => 'ACTIVE',
        ]);

        $assignment = $this->postJson('/api/assignments', [
            'place_id' => $place->id,
            'merchant_id' => $merchant->id,
            'start_date' => '2026-08-01',
            'rent_amount' => 50000,
            'status' => 'ACTIVE',
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        $this->assertDatabaseHas('place_assignments', [
            'id' => $assignment['id'],
            'status' => 'ACTIVE',
        ]);

        $this->postJson("/api/assignments/{$assignment['id']}/terminate", [
            'end_date' => '2026-08-20',
            'reason' => 'Départ',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'ENDED');
    }

    public function test_overlapping_assignment_is_rejected(): void
    {
        [, $token] = $this->makeUserWithToken('ADMIN', ['assignments.manage', 'merchants.manage', 'places.manage', 'blocks.manage']);

        $block = Block::query()->create([
            'code' => 'BLK-2',
            'name' => 'Bloc 2',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ]);

        $place = Place::query()->create([
            'block_id' => $block->id,
            'code' => 'PL-2',
            'name' => 'Place 2',
            'status' => 'AVAILABLE',
        ]);

        $merchant = Merchant::query()->create([
            'merchant_code' => 'MER-2',
            'business_name' => 'Commerce 2',
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
            ->assertUnprocessable();
    }
}
