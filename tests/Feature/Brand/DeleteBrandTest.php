<?php

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Covers BrandController::destroy()'s explicit block: brand_models.brand_id
 * is cascadeOnDelete(), so deleting a brand with models linked would
 * silently wipe out every one of them. destroy() blocks with a 422 and a
 * specific message instead -- the frontend surfaces that message verbatim
 * via errorBag.general, so it needs to actually be present in the response.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    $role = Role::create(['title' => 'Brand Tester']);

    $permissionIds = collect(['brands.view', 'brands.delete'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);
});

test('deleting an unused brand succeeds', function () {
    $brand = Brand::factory()->create();

    $response = $this->deleteJson("/api/brands/{$brand->id}");

    $response->assertSuccessful();
    expect(Brand::find($brand->id))->toBeNull();
});

test('deleting a brand with linked brand models is blocked with a specific message', function () {
    $brand = Brand::factory()->create();
    BrandModel::factory()->count(2)->create(['brand_id' => $brand->id]);

    $response = $this->deleteJson("/api/brands/{$brand->id}");

    $response->assertStatus(422);
    $response->assertJsonFragment([
        'message' => "Cannot delete \"{$brand->name}\": 2 brand model(s) are still linked to it. Reassign or delete those first.",
    ]);
    expect(Brand::find($brand->id))->not->toBeNull();
});
