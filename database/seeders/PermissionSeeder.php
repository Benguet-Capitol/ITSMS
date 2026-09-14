<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the exact 95 permissions as they exist in production (snapshot
     * captured 2026-09-03). ids 1-66 are the original 2026-06-24 base set;
     * ids 67-95 were added later on 2026-08-24 (ticket lifecycle actions,
     * search/select lookups). IDs are preserved exactly to match the
     * permission_role snapshot in PermissionRoleTableSeeder.
     */
    public function run(): void
    {
        $base = '2026-06-24 15:25:54';
        $later = '2026-08-24 09:28:03';

        $baseTitles = [
            1 => 'dashboard.view',
            2 => 'tickets.view', 3 => 'tickets.create', 4 => 'tickets.update', 5 => 'tickets.delete', 6 => 'tickets.print_assessment',
            7 => 'inventories.view', 8 => 'inventories.create', 9 => 'inventories.update', 10 => 'inventories.delete', 11 => 'inventories.report',
            12 => 'it_supplies.view', 13 => 'it_supplies.create', 14 => 'it_supplies.update', 15 => 'it_supplies.delete',
            16 => 'solutions.view', 17 => 'solutions.create', 18 => 'solutions.update', 19 => 'solutions.delete',
            20 => 'users.view', 21 => 'users.create', 22 => 'users.update', 23 => 'users.delete',
            24 => 'roles.view', 25 => 'roles.create', 26 => 'roles.update', 27 => 'roles.delete',
            28 => 'permissions.view', 29 => 'permissions.create', 30 => 'permissions.update', 31 => 'permissions.delete',
            32 => 'agencies.view', 33 => 'agencies.create', 34 => 'agencies.update', 35 => 'agencies.delete',
            36 => 'departments.view', 37 => 'departments.create', 38 => 'departments.update', 39 => 'departments.delete',
            40 => 'brands.view', 41 => 'brands.create', 42 => 'brands.update', 43 => 'brands.delete',
            44 => 'brand_models.view', 45 => 'brand_models.create', 46 => 'brand_models.update', 47 => 'brand_models.delete',
            48 => 'item_types.view', 49 => 'item_types.create', 50 => 'item_types.update', 51 => 'item_types.delete',
            52 => 'it_services.view', 53 => 'it_services.create', 54 => 'it_services.update', 55 => 'it_services.delete',
            56 => 'measurement_units.view', 57 => 'measurement_units.create', 58 => 'measurement_units.update', 59 => 'measurement_units.delete',
            60 => 'requests.other_it_services.view', 61 => 'requests.other_it_services.create', 62 => 'requests.other_it_services.update', 63 => 'requests.other_it_services.print', 64 => 'requests.other_it_services.delete',
            65 => 'employees.view', 66 => 'offices.view',
        ];

        $laterTitles = [
            67 => 'tickets.accept', 68 => 'tickets.unaccept', 69 => 'tickets.check_stock', 70 => 'tickets.await_part',
            71 => 'tickets.resolve', 72 => 'tickets.cancel', 73 => 'tickets.reopen', 74 => 'tickets.set_service_method',
            75 => 'tickets.set_release_date', 76 => 'tickets.assess', 77 => 'tickets.search',
            78 => 'inventories.search', 79 => 'it_supplies.search',
            80 => 'solutions.select', 81 => 'solutions.search',
            82 => 'roles.select',
            83 => 'agencies.select', 84 => 'agencies.search',
            85 => 'departments.select',
            86 => 'brands.select', 87 => 'brands.search',
            88 => 'brand_models.select', 89 => 'brand_models.search',
            90 => 'item_types.select', 91 => 'item_types.search',
            92 => 'it_services.select',
            93 => 'measurement_units.select',
            94 => 'employees.search', 95 => 'offices.search',
        ];

        // Ticket Complexity Levels module (added 2026-09-09, replacing the
        // hardcoded priority/complexity enum with an admin-managed table).
        $newestTitles = [
            96 => 'ticket_complexity_levels.view', 97 => 'ticket_complexity_levels.create',
            98 => 'ticket_complexity_levels.update', 99 => 'ticket_complexity_levels.delete',
            100 => 'ticket_complexity_levels.select',
        ];

        // Common Problems module (permissions added 2026-09-10): the table,
        // model, CRUD screen and requests already existed, but no
        // common_problems.* permission was ever seeded, so every
        // Gate::authorize('common_problems.*') call in
        // CommonProblemController was unreachable for every role,
        // including Admin. Added here alongside the new .select lookup
        // that wires it into Tickets' concern field.
        $commonProblemsTitles = [
            101 => 'common_problems.view', 102 => 'common_problems.create',
            103 => 'common_problems.update', 104 => 'common_problems.delete',
            105 => 'common_problems.select',
        ];

        $rows = [];
        foreach ($baseTitles as $id => $title) {
            $rows[] = ['id' => $id, 'title' => $title, 'created_at' => $base, 'updated_at' => $base];
        }
        foreach ($laterTitles as $id => $title) {
            $rows[] = ['id' => $id, 'title' => $title, 'created_at' => $later, 'updated_at' => $later];
        }
        foreach ($newestTitles as $id => $title) {
            $rows[] = ['id' => $id, 'title' => $title, 'created_at' => now(), 'updated_at' => now()];
        }
        foreach ($commonProblemsTitles as $id => $title) {
            $rows[] = ['id' => $id, 'title' => $title, 'created_at' => now(), 'updated_at' => now()];
        }

        DB::table('permissions')->insert($rows);
    }
}
