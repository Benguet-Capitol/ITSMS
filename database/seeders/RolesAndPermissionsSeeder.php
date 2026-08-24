<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use RuntimeException;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // ──────────────────────────────────────────
        // 1. Define all permissions
        // ──────────────────────────────────────────
        $permissions = [

            // Dashboard
            'dashboard.view',

            // Tickets
            'tickets.view',
            'tickets.create',
            'tickets.update',
            'tickets.delete',
            'tickets.accept',
            'tickets.unaccept',
            'tickets.check_stock',
            'tickets.await_part',
            'tickets.resolve',
            'tickets.cancel',
            'tickets.reopen',
            'tickets.set_service_method',
            'tickets.set_release_date',
            'tickets.assess',
            'tickets.print_assessment',
            'tickets.search',

            // Inventory
            'inventories.view',
            'inventories.create',
            'inventories.update',
            'inventories.delete',
            'inventories.report',
            'inventories.search',

            // IT Supplies
            'it_supplies.view',
            'it_supplies.create',
            'it_supplies.update',
            'it_supplies.delete',
            'it_supplies.search',

            // Solutions (Knowledge Base)
            'solutions.view',
            'solutions.create',
            'solutions.update',
            'solutions.delete',
            'solutions.select',
            'solutions.search',

            // Control Panel — Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Control Panel — Roles & Permissions
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'roles.select',

            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',

            // Control Panel — Reference Data
            'agencies.view',
            'agencies.create',
            'agencies.update',
            'agencies.delete',
            'agencies.select',
            'agencies.search',

            'departments.view',
            'departments.create',
            'departments.update',
            'departments.delete',
            'departments.select',

            // 'positions.view',
            // 'positions.create',
            // 'positions.update',
            // 'positions.delete',
            // 'positions.select',

            'brands.view',
            'brands.create',
            'brands.update',
            'brands.delete',
            'brands.select',
            'brands.search',

            'brand_models.view',
            'brand_models.create',
            'brand_models.update',
            'brand_models.delete',
            'brand_models.select',
            'brand_models.search',

            'item_types.view',
            'item_types.create',
            'item_types.update',
            'item_types.delete',
            'item_types.select',
            'item_types.search',

            'it_services.view',
            'it_services.create',
            'it_services.update',
            'it_services.delete',
            'it_services.select',

            'measurement_units.view',
            'measurement_units.create',
            'measurement_units.update',
            'measurement_units.delete',
            'measurement_units.select',

            // 'common_problems.view',
            // 'common_problems.create',
            // 'common_problems.update',
            // 'common_problems.delete',

            'requests.other_it_services.view',
            'requests.other_it_services.create',
            'requests.other_it_services.update',
            'requests.other_it_services.print',
            'requests.other_it_services.delete',

            // HRIS Proxies (read-only)
            'employees.view',
            'employees.search',
            'offices.view',
            'offices.search',

        ];

        foreach ($permissions as $title) {
            Permission::firstOrCreate(['title' => $title]);
        }

        // ──────────────────────────────────────────
        // 2. Define roles and their permission sets
        // ──────────────────────────────────────────
        $roles = [

            'Admin' => Permission::all()->pluck('title')->toArray(),

            'IT Admin Staff' => [
                'dashboard.view',

                // Tickets — intake only (create + view; no lifecycle transitions)
                // Tickets — intake, editing, and service-method assignment
                'tickets.view',
                'tickets.create',
                'tickets.update',
                'tickets.set_service_method',
                'tickets.print_assessment',
                'tickets.search',

                // Inventory — full CRUD + reports
                'inventories.view',
                'inventories.create',
                'inventories.update',
                'inventories.delete',
                'inventories.report',
                'inventories.search',

                // IT Supplies — full CRUD
                'it_supplies.view',
                'it_supplies.create',
                'it_supplies.update',
                'it_supplies.delete',

                // Solutions — view only
                'solutions.view',
                'solutions.select',

                // Control Panel — full management
                'users.view',
                'users.create',
                'users.update',
                'users.delete',

                // 'roles.view',
                // 'roles.create',
                // 'roles.update',
                // 'roles.delete',

                // 'permissions.view',
                // 'permissions.create',
                // 'permissions.update',
                // 'permissions.delete',

                'agencies.view',
                'agencies.create',
                'agencies.update',
                'agencies.delete',
                'agencies.select',
                'agencies.search',

                // 'departments.view',
                // 'departments.create',
                // 'departments.update',
                // 'departments.delete',

                // 'positions.view',
                // 'positions.create',
                // 'positions.update',
                // 'positions.delete',

                'brands.view',
                'brands.create',
                'brands.update',
                'brands.delete',
                'brands.search',

                'brand_models.view',
                'brand_models.create',
                'brand_models.update',
                'brand_models.delete',
                'brand_models.search',

                'item_types.view',
                'item_types.create',
                'item_types.update',
                'item_types.delete',
                'item_types.search',

                'it_services.view',
                'it_services.create',
                'it_services.update',
                'it_services.delete',

                'measurement_units.view',
                'measurement_units.create',
                'measurement_units.update',
                'measurement_units.delete',

                // 'common_problems.view',
                // 'common_problems.create',
                // 'common_problems.update',
                // 'common_problems.delete',

                'requests.other_it_services.view',
                'requests.other_it_services.create',
                'requests.other_it_services.update',
                'requests.other_it_services.print',
                'requests.other_it_services.delete',

                'brands.select',
                'brand_models.select',
                'item_types.select',
                'it_services.select',
                'measurement_units.select',

                'employees.view',
                'offices.view',
                
                'offices.search',
            ],

            'IT Technical' => [
                'dashboard.view',

                // Tickets — full lifecycle
                'tickets.view',
                'tickets.update',
                'tickets.delete',
                
                'tickets.accept',
                'tickets.unaccept',
                'tickets.check_stock',
                'tickets.await_part',
                'tickets.resolve',
                'tickets.cancel',
                'tickets.reopen',
                'tickets.set_service_method',
                'tickets.set_release_date',
                'tickets.assess',
                'tickets.print_assessment',
                'tickets.search',

                // Inventory — view + update (no create/delete)
                'inventories.view',
                'inventories.update',
                'inventories.report',
                'inventories.search',

                // IT Supplies — full CRUD
                'it_supplies.view',
                'it_supplies.create',
                'it_supplies.update',
                'it_supplies.delete',
                'it_supplies.search',

                // Solutions — full CRUD
                'solutions.view',
                'solutions.create',
                'solutions.update',
                'solutions.delete',
                'solutions.select',
                'solutions.search',

                // Reference data — view only
                // 'agencies.view',
                // 'departments.view',
                // 'positions.view',
                // 'brands.view',
                // 'brand_models.view',
                // 'item_types.view',
                // 'it_services.view',
                // 'measurement_units.view',
                // 'common_problems.view',

                'requests.other_it_services.view',
                'requests.other_it_services.create',
                'requests.other_it_services.update',
                'requests.other_it_services.print',
                'requests.other_it_services.delete',

                'brands.select',
                'brand_models.select',
                'item_types.select',
                'it_services.select',
                'measurement_units.select',
                'agencies.select',

                'employees.view',
                'offices.view',
            ],

            'Encoder' => [
                'dashboard.view',

                // Inventory — full CRUD
                'inventories.view',
                'inventories.create',
                'inventories.update',
                // 'inventories.delete',

                // Control Panel — full management
                'brands.view',
                'brands.create',
                'brands.update',
                'brands.delete',

                'brand_models.view',
                'brand_models.create',
                'brand_models.update',
                'brand_models.delete',

                'item_types.view',
                'item_types.create',
                'item_types.update',
                'item_types.delete',

                'it_services.view',
                'it_services.create',
                'it_services.update',
                'it_services.delete',

                'measurement_units.view',
                'measurement_units.create',
                'measurement_units.update',
                'measurement_units.delete',

                'brands.select',
                'brand_models.select',
                'item_types.select',
                'it_services.select',
                'measurement_units.select',

                'employees.view',
                'offices.view',

            ],

            'User' => [
              'dashboard.view',

              'tickets.view',
              'tickets.create',
              'tickets.search',

              'inventories.search',
              'agencies.select',
              'agencies.search',
              'offices.search',

              'brands.select',
              'brand_models.select',
              'item_types.select',
              'it_services.select',
              'measurement_units.select',

              'employees.view',
          ],
        ];

        foreach ($roles as $roleTitle => $permissionTitles) {
            $role = Role::firstOrCreate(['title' => $roleTitle]);

            $existingPermissions = Permission::whereIn(
                'title',
                $permissionTitles
            )->pluck('title')->all();

            $missingPermissions = array_diff(
                $permissionTitles,
                $existingPermissions
            );

            if ($missingPermissions !== []) {
                throw new RuntimeException(
                    "Role [{$roleTitle}] references missing permissions: "
                    . implode(', ', $missingPermissions)
                );
            }

            $ids = Permission::whereIn('title', $permissionTitles)
                ->pluck('id');

            $role->permissions()->sync($ids);
        }

        $this->command->info('Roles and permissions seeded successfully.');
    }
}