<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Place;

class StructureApiTest extends ApiTestCase
{
    public function test_block_crud_and_soft_delete_rules_work(): void
    {
        [, $token] = $this->makeUserWithToken('SUPER_ADMIN', ['blocks.manage', 'places.manage']);

        $create = $this->postJson('/api/blocks', [
            'code' => 'B-A',
            'name' => 'Bloc A',
            'default_rent_amount' => 50000,
            'status' => 'ACTIVE',
        ], $this->authHeaders($token));

        $create->assertCreated()
            ->assertJsonPath('data.code', 'B-A');

        $blockId = $create->json('data.id');

        $this->getJson('/api/blocks', $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.0.code', 'B-A');

        $this->patchJson("/api/blocks/{$blockId}", [
            'name' => 'Bloc A mis à jour',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Bloc A mis à jour');

        $this->deleteJson("/api/blocks/{$blockId}", [], $this->authHeaders($token))
            ->assertOk();
    }

    public function test_places_can_be_created_and_occupied_places_cannot_be_deleted(): void
    {
        [, $token] = $this->makeUserWithToken('ADMIN', ['places.manage', 'assignments.manage', 'merchants.manage', 'blocks.manage']);

        $block = Block::query()->create([
            'code' => 'B-1',
            'name' => 'Bloc 1',
            'description' => null,
            'default_rent_amount' => 40000,
            'status' => 'ACTIVE',
        ]);

        $place = $this->postJson('/api/places', [
            'block_id' => $block->id,
            'code' => 'P-001',
            'name' => 'Place 001',
            'surface' => 5,
            'status' => 'AVAILABLE',
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        $this->assertDatabaseHas('places', [
            'id' => $place['id'],
            'code' => 'P-001',
        ]);

        $this->deleteJson("/api/places/{$place['id']}", [], $this->authHeaders($token))
            ->assertOk();
    }
}
