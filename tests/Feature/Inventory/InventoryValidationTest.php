<?php

use App\Models\BrandModel;
use App\Models\Inventory;
use App\Models\ItemType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Covers the cross-field business rules enforced by StoreInventoryRequest
 * and UpdateInventoryRequest (parent/component/internal-component
 * constraints), plus the property_number uniqueness rule. UpdateInventoryRequest
 * previously only checked one of the four rules Store enforced -- these
 * tests pin down parity between the two, including the item_type_id
 * fallback added to make the rules apply even when a partial update
 * omits item_type_id.
 */
function inventoryPayload(ItemType $itemType, array $overrides = []): array
{
    return array_merge([
        'item_type_id' => $itemType->id,
        'property_number' => 'PROP-'.uniqid(),
        'status' => 'active',
    ], $overrides);
}

beforeEach(function () {
    $this->user = User::factory()->create();

    $role = Role::create(['title' => 'Inventory Tester']);

    $permissionIds = collect([
        'inventories.view',
        'inventories.create',
        'inventories.update',
        'inventories.delete',
        'inventories.search',
    ])->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->user->roles()->attach($role->id);

    $this->actingAs($this->user);
});

// ── Store ────────────────────────────────────────────────────────────────

test('creating a standalone main-inventory item succeeds', function () {
    $itemType = ItemType::factory()->create([
        'is_main_inventory' => true,
        'is_component' => false,
        'supports_internal_components' => false,
    ]);

    $response = $this->postJson('/api/inventories', inventoryPayload($itemType));

    $response->assertSuccessful();
    $this->assertDatabaseHas('inventories', ['item_type_id' => $itemType->id]);
});

test('a non-component item type cannot be attached as a child component', function () {
    $parent = Inventory::factory()->create();

    $nonComponentType = ItemType::factory()->create([
        'is_main_inventory' => false,
        'is_component' => false,
    ]);

    $brandModel = BrandModel::factory()->create(['item_type_id' => $nonComponentType->id]);

    $response = $this->postJson('/api/inventories', inventoryPayload($nonComponentType, [
        'parent_component_id' => $parent->id,
        'brand_model_id' => $brandModel->id,
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('parent_component_id');
});

test('a child component cannot carry internal components', function () {
    $parent = Inventory::factory()->create();

    $componentType = ItemType::factory()->create([
        'is_main_inventory' => false,
        'is_component' => true,
    ]);

    $brandModel = BrandModel::factory()->create(['item_type_id' => $componentType->id]);
    $internalBrandModel = BrandModel::factory()->create();

    $response = $this->postJson('/api/inventories', inventoryPayload($componentType, [
        'parent_component_id' => $parent->id,
        'brand_model_id' => $brandModel->id,
        'internal_components' => [
            ['brand_model' => ['id' => $internalBrandModel->id], 'quantity' => 1],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('internal_components');
});

test('a non-main-inventory item type cannot carry internal components', function () {
    $itemType = ItemType::factory()->create([
        'is_main_inventory' => false,
        'is_component' => false,
    ]);

    $internalBrandModel = BrandModel::factory()->create();

    $response = $this->postJson('/api/inventories', inventoryPayload($itemType, [
        'internal_components' => [
            ['brand_model' => ['id' => $internalBrandModel->id], 'quantity' => 1],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('internal_components');
});

test('a child component requires a brand model', function () {
    $parent = Inventory::factory()->create();

    $componentType = ItemType::factory()->create([
        'is_main_inventory' => false,
        'is_component' => true,
    ]);

    $response = $this->postJson('/api/inventories', inventoryPayload($componentType, [
        'parent_component_id' => $parent->id,
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('brand_model_id');
});

test('property number must be unique on create', function () {
    $itemType = ItemType::factory()->create(['is_main_inventory' => true]);

    Inventory::factory()->create(['property_number' => 'DUP-001']);

    $response = $this->postJson('/api/inventories', inventoryPayload($itemType, [
        'property_number' => 'DUP-001',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('property_number');
});

// ── Update ───────────────────────────────────────────────────────────────

test('updating a standalone main-inventory item succeeds', function () {
    $itemType = ItemType::factory()->create([
        'is_main_inventory' => true,
        'is_component' => false,
        'supports_internal_components' => false,
    ]);

    $inventory = Inventory::factory()->create(['item_type_id' => $itemType->id]);

    $response = $this->putJson("/api/inventories/{$inventory->id}", inventoryPayload($itemType, [
        'property_number' => $inventory->property_number,
        'remarks' => 'updated remarks',
    ]));

    $response->assertSuccessful();
});

test('updating an inventory to its own property number does not trigger a uniqueness error', function () {
    $itemType = ItemType::factory()->create(['is_main_inventory' => true]);

    $inventory = Inventory::factory()->create([
        'item_type_id' => $itemType->id,
        'property_number' => 'KEEP-001',
    ]);

    $response = $this->putJson("/api/inventories/{$inventory->id}", inventoryPayload($itemType, [
        'property_number' => 'KEEP-001',
    ]));

    $response->assertSuccessful();
});

test('update rejects a non-component item type attached as a child component', function () {
    $parent = Inventory::factory()->create();

    $nonComponentType = ItemType::factory()->create([
        'is_main_inventory' => false,
        'is_component' => false,
    ]);

    $inventory = Inventory::factory()->create(['item_type_id' => $nonComponentType->id]);
    $brandModel = BrandModel::factory()->create(['item_type_id' => $nonComponentType->id]);

    $response = $this->putJson("/api/inventories/{$inventory->id}", inventoryPayload($nonComponentType, [
        'property_number' => $inventory->property_number,
        'parent_component_id' => $parent->id,
        'brand_model_id' => $brandModel->id,
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('parent_component_id');
});

test('update rejects internal components on a child component', function () {
    $parent = Inventory::factory()->create();

    $componentType = ItemType::factory()->create([
        'is_main_inventory' => false,
        'is_component' => true,
    ]);

    $inventory = Inventory::factory()->create(['item_type_id' => $componentType->id]);
    $brandModel = BrandModel::factory()->create(['item_type_id' => $componentType->id]);
    $internalBrandModel = BrandModel::factory()->create();

    $response = $this->putJson("/api/inventories/{$inventory->id}", inventoryPayload($componentType, [
        'property_number' => $inventory->property_number,
        'parent_component_id' => $parent->id,
        'brand_model_id' => $brandModel->id,
        'internal_components' => [
            ['brand_model' => ['id' => $internalBrandModel->id], 'quantity' => 1],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('internal_components');
});

test('update rejects internal components on a non-main-inventory item type', function () {
    $itemType = ItemType::factory()->create([
        'is_main_inventory' => false,
        'is_component' => false,
    ]);

    $inventory = Inventory::factory()->create(['item_type_id' => $itemType->id]);
    $internalBrandModel = BrandModel::factory()->create();

    $response = $this->putJson("/api/inventories/{$inventory->id}", inventoryPayload($itemType, [
        'property_number' => $inventory->property_number,
        'internal_components' => [
            ['brand_model' => ['id' => $internalBrandModel->id], 'quantity' => 1],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('internal_components');
});

test('update rejects a child component missing a brand model', function () {
    $parent = Inventory::factory()->create();

    $componentType = ItemType::factory()->create([
        'is_main_inventory' => false,
        'is_component' => true,
    ]);

    $inventory = Inventory::factory()->create(['item_type_id' => $componentType->id]);

    $response = $this->putJson("/api/inventories/{$inventory->id}", inventoryPayload($componentType, [
        'property_number' => $inventory->property_number,
        'parent_component_id' => $parent->id,
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('brand_model_id');
});

test('update falls back to the existing item type when item_type_id is omitted from the request', function () {
    $parent = Inventory::factory()->create();

    $componentType = ItemType::factory()->create([
        'is_main_inventory' => false,
        'is_component' => true,
    ]);

    $inventory = Inventory::factory()->create(['item_type_id' => $componentType->id]);

    // item_type_id is deliberately omitted: this is the actual gap that was
    // fixed. The request must fall back to the inventory's existing item
    // type to still enforce "a child component needs a brand model" --
    // previously, omitting item_type_id skipped every one of these checks.
    $payload = inventoryPayload($componentType, [
        'property_number' => $inventory->property_number,
        'parent_component_id' => $parent->id,
    ]);
    unset($payload['item_type_id']);

    $response = $this->putJson("/api/inventories/{$inventory->id}", $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('brand_model_id');
});
