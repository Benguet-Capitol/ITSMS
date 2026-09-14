<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionRoleTableSeeder extends Seeder
{
    /**
     * Exact permission_role pivot snapshot from production (2026-09-03).
     * Preserves the original base-block / later-addition grouping from
     * the source data for auditability, even though only the final
     * union matters functionally.
     */
    public function run(): void
    {
        $pairs = [
            // Role 1 (Admin) — base block, permissions 1-66
            [1, 1], [2, 1], [3, 1], [4, 1], [5, 1], [6, 1], [7, 1], [8, 1], [9, 1], [10, 1],
            [11, 1], [12, 1], [13, 1], [14, 1], [15, 1], [16, 1], [17, 1], [18, 1], [19, 1], [20, 1],
            [21, 1], [22, 1], [23, 1], [24, 1], [25, 1], [26, 1], [27, 1], [28, 1], [29, 1], [30, 1],
            [31, 1], [32, 1], [33, 1], [34, 1], [35, 1], [36, 1], [37, 1], [38, 1], [39, 1], [40, 1],
            [41, 1], [42, 1], [43, 1], [44, 1], [45, 1], [46, 1], [47, 1], [48, 1], [49, 1], [50, 1],
            [51, 1], [52, 1], [53, 1], [54, 1], [55, 1], [56, 1], [57, 1], [58, 1], [59, 1], [60, 1],
            [61, 1], [62, 1], [63, 1], [64, 1], [65, 1], [66, 1],

            // Role 2 (It Admin Staff) — base block
            [1, 2], [2, 2], [3, 2], [6, 2], [7, 2], [8, 2], [9, 2], [10, 2], [11, 2], [12, 2],
            [13, 2], [14, 2], [15, 2], [16, 2], [20, 2], [21, 2], [22, 2], [23, 2], [32, 2], [33, 2],
            [34, 2], [35, 2], [40, 2], [41, 2], [42, 2], [43, 2], [44, 2], [45, 2], [46, 2], [47, 2],
            [48, 2], [49, 2], [50, 2], [51, 2], [52, 2], [53, 2], [54, 2], [55, 2], [56, 2], [57, 2],
            [58, 2], [59, 2], [60, 2], [61, 2], [62, 2], [63, 2], [64, 2], [65, 2], [66, 2],

            // Role 3 (It Technical) — base block
            [1, 3], [2, 3], [4, 3], [5, 3], [6, 3], [7, 3], [9, 3], [11, 3], [12, 3], [13, 3],
            [14, 3], [15, 3], [16, 3], [17, 3], [18, 3], [19, 3], [60, 3], [61, 3], [62, 3], [63, 3],
            [64, 3], [65, 3], [66, 3],

            // Role 4 (Encoder) — base block
            [1, 4], [7, 4], [8, 4], [9, 4], [40, 4], [41, 4], [42, 4], [43, 4], [44, 4], [45, 4],
            [46, 4], [47, 4], [48, 4], [49, 4], [50, 4], [51, 4], [52, 4], [53, 4], [54, 4], [55, 4],
            [56, 4], [57, 4], [58, 4], [59, 4], [65, 4], [66, 4],

            // Role 5 (User) — base block
            [1, 5], [2, 5], [3, 5], [65, 5],

            // Later additions (permissions 67-95 rolled out progressively per role)
            [4, 2],
            [67, 1], [68, 1], [69, 1], [70, 1], [71, 1], [72, 1], [73, 1], [74, 1], [75, 1], [76, 1],
            [77, 1], [78, 1], [79, 1], [80, 1], [81, 1], [82, 1], [83, 1], [84, 1], [85, 1], [86, 1],
            [87, 1], [88, 1], [89, 1], [90, 1], [91, 1], [92, 1], [93, 1], [94, 1], [95, 1],
            [74, 2], [77, 2], [78, 2], [80, 2], [83, 2], [84, 2], [86, 2], [87, 2], [88, 2], [89, 2],
            [90, 2], [91, 2], [92, 2], [93, 2], [95, 2],
            [67, 3], [68, 3], [69, 3], [70, 3], [71, 3], [72, 3], [73, 3], [74, 3], [75, 3], [76, 3],
            [77, 3], [78, 3], [79, 3], [80, 3], [81, 3], [83, 3], [86, 3], [88, 3], [90, 3], [92, 3], [93, 3],
            [86, 4], [88, 4], [90, 4], [92, 4], [93, 4],
            [77, 5], [78, 5], [83, 5], [84, 5], [86, 5], [88, 5], [90, 5], [92, 5], [93, 5], [95, 5],
            [3, 3],
            [17, 2], [18, 2], [81, 2], [94, 2],
            [84, 3],

            // Ticket Complexity Levels module (added 2026-09-09): Admin and
            // It Admin Staff manage the lookup table (mirrors their
            // measurement_units access); It Technical and User only need
            // to select a level when creating/editing a ticket (mirrors
            // their item_types.select/measurement_units.select access).
            [96, 1], [97, 1], [98, 1], [99, 1], [100, 1],
            [96, 2], [97, 2], [98, 2], [99, 2], [100, 2],
            [100, 3],
            [100, 5],

            // Common Problems module (added 2026-09-10): CRUD management
            // granted to Admin/It Admin Staff/Encoder, mirroring the
            // existing Brand Models reference-data pattern (permissions
            // 44-47) -- another item-type-scoped lookup table Encoder
            // already maintains. .select is granted to whichever roles can
            // actually create/update tickets (mirrors tickets.create,
            // permission 3), since that's the only place it's consumed.
            [101, 1], [102, 1], [103, 1], [104, 1], [105, 1],
            [101, 2], [102, 2], [103, 2], [104, 2], [105, 2],
            [101, 4], [102, 4], [103, 4], [104, 4],
            [105, 3],
            [105, 5],
        ];

        $rows = array_map(fn ($p) => ['permission_id' => $p[0], 'role_id' => $p[1]], $pairs);

        DB::table('permission_role')->insert($rows);
    }
}
