<?php

use App\Models\Inventory;
use App\Models\ItemType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Sprint 3 exit-gate items that existed only as request-level validation
 * before (see InventoryValidationTest's "property number must be unique on
 * create") -- these cover the actual DB-level guarantees added in
 * migration 2026_09_10_120000: a unique index on property_number (so two
 * concurrent requests that both pass validation before either commits
 * can't both insert the same value) and a `public_id` (ULID) column.
 */
test('property_number is rejected by the database itself, not just request validation', function () {
    Inventory::factory()->create(['property_number' => 'DB-UNIQUE-001']);

    expect(fn () => Inventory::factory()->create(['property_number' => 'DB-UNIQUE-001']))
        ->toThrow(QueryException::class);
});

test('a new inventory is auto-assigned a unique public_id', function () {
    $inventory = Inventory::factory()->create();

    expect($inventory->public_id)->not->toBeNull();
    expect(strlen($inventory->public_id))->toBe(26);
});

test('two inventories never receive the same public_id', function () {
    $first = Inventory::factory()->create();
    $second = Inventory::factory()->create();

    expect($first->public_id)->not->toBe($second->public_id);
});

test('a client cannot set public_id through the create endpoint', function () {
    $user = User::factory()->create();
    $role = Role::create(['title' => 'Inventory Public Id Tester']);
    $role->permissions()->attach(
        Permission::create(['title' => 'inventories.create'])->id,
    );
    $user->roles()->attach($role->id);
    $this->actingAs($user);

    $itemType = ItemType::factory()->create(['is_main_inventory' => true]);

    $response = $this->postJson('/api/inventories', [
        'item_type_id' => $itemType->id,
        'property_number' => 'PUBLIC-ID-SPOOF-001',
        'status' => 'active',
        'public_id' => 'not-a-real-ulid',
    ]);

    $response->assertSuccessful();

    $inventory = Inventory::where('property_number', 'PUBLIC-ID-SPOOF-001')->firstOrFail();

    expect($inventory->public_id)->not->toBe('not-a-real-ulid');
    expect(strlen($inventory->public_id))->toBe(26);
});
