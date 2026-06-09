<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

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
            'tickets.print_assessment',

            // Inventory
            'inventories.view',
            'inventories.create',
            'inventories.update',
            'inventories.delete',
            'inventories.report',

            // IT Supplies
            'it_supplies.view',
            'it_supplies.create',
            'it_supplies.update',
            'it_supplies.delete',

            // Solutions (Knowledge Base)
            'solutions.view',
            'solutions.create',
            'solutions.update',
            'solutions.delete',

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
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',

            // Control Panel — Reference Data
            'agencies.view',
            'agencies.create',
            'agencies.update',
            'agencies.delete',

            'departments.view',
            'departments.create',
            'departments.update',
            'departments.delete',

            // 'positions.view',
            // 'positions.create',
            // 'positions.update',
            // 'positions.delete',

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

            // 'common_problems.view',
            // 'common_problems.create',
            // 'common_problems.update',
            // 'common_problems.delete',

            // HRIS Proxies (read-only)
            'employees.view',
            'offices.view',
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
                'tickets.view',
                'tickets.create',
                'tickets.print_assessment',

                // Inventory — full CRUD + reports
                'inventories.view',
                'inventories.create',
                'inventories.update',
                'inventories.delete',
                'inventories.report',

                // IT Supplies — full CRUD
                'it_supplies.view',
                'it_supplies.create',
                'it_supplies.update',
                'it_supplies.delete',

                // Solutions — view only
                'solutions.view',

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

                // 'common_problems.view',
                // 'common_problems.create',
                // 'common_problems.update',
                // 'common_problems.delete',

                // 'employees.view',
                'offices.view',
            ],

            'IT Technical' => [
                'dashboard.view',

                // Tickets — full lifecycle
                'tickets.view',
                'tickets.create',
                'tickets.update',
                'tickets.delete',
                'tickets.print_assessment',

                // Inventory — view + update (no create/delete)
                'inventories.view',
                'inventories.update',
                'inventories.report',

                // IT Supplies — full CRUD
                'it_supplies.view',
                'it_supplies.create',
                'it_supplies.update',
                'it_supplies.delete',

                // Solutions — full CRUD
                'solutions.view',
                'solutions.create',
                'solutions.update',
                'solutions.delete',

                // Reference data — view only
                'agencies.view',
                // 'departments.view',
                // 'positions.view',
                'brands.view',
                'brand_models.view',
                'item_types.view',
                'it_services.view',
                'measurement_units.view',
                // 'common_problems.view',

                // 'employees.view',
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

                'offices.view',

            ],

            'User' => [
                'dashboard.view',
                'tickets.view',
                'tickets.create',
            ],
        ];

        foreach ($roles as $roleTitle => $permissionTitles) {
            $role = Role::firstOrCreate(['title' => $roleTitle]);

            $ids = Permission::whereIn('title', $permissionTitles)->pluck('id');
            $role->permissions()->sync($ids);
        }

        $this->command->info('Roles and permissions seeded successfully.');
    }
}