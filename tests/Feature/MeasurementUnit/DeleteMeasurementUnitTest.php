<?php

use App\Models\ItSupply;
use App\Models\MeasurementUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Covers a real bug: it_supplies.measurement_unit_id is cascadeOnDelete(),
 * so deleting a measurement unit still referenced by an IT supply used to
 * silently wipe out that supply record. destroy() now blocks the delete
 * with a 422 while the unit is still in use.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    $role = Role::create(['title' => 'Measurement Unit Tester']);

    $permissionIds = collect(['measurement_units.view', 'measurement_units.delete'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);
});

test('deleting a measurement unit still in use is blocked', function () {
    $measurementUnit = MeasurementUnit::factory()->create();
    ItSupply::factory()->create(['measurement_unit_id' => $measurementUnit->id]);

    $response = $this->deleteJson("/api/measurement-units/{$measurementUnit->id}");

    $response->assertStatus(422);
    expect(MeasurementUnit::find($measurementUnit->id))->not->toBeNull();
});

test('deleting an unused measurement unit succeeds', function () {
    $measurementUnit = MeasurementUnit::factory()->create();

    $response = $this->deleteJson("/api/measurement-units/{$measurementUnit->id}");

    $response->assertSuccessful();
    expect(MeasurementUnit::find($measurementUnit->id))->toBeNull();
});
