<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The IT office's wall display (/display):
 *
 * - `display.view`, the permission to open it.
 * - "Wall Display", a role with only that permission, for the account the
 *   TV signs in with. It is kept off the staff presence lists.
 * - The admin roles get display.view too, so they can open it from the
 *   sidebar.
 *
 * Matched by title, not id -- local and production ids differ. Safe to
 * re-run: nothing is created twice, and permission_role (no unique key)
 * only gets the rows it is missing. Skipped on a database with none of
 * the admin roles (an empty or test database), where inserting would
 * collide with the seeders' fixed ids.
 */
return new class extends Migration
{
    private const PERMISSION = 'display.view';

    private const ROLE = 'Wall Display';

    private const ADMIN_ROLES = ['Admin', 'It Admin Staff', 'It Admin Staff (No Delete)'];

    public function up(): void
    {
        if (! DB::table('roles')->whereIn('title', self::ADMIN_ROLES)->exists()) {
            return;
        }

        $permissionId = DB::table('permissions')->where('title', self::PERMISSION)->value('id')
            ?? DB::table('permissions')->insertGetId([
                'title' => self::PERMISSION,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $roleId = DB::table('roles')->where('title', self::ROLE)->value('id')
            ?? DB::table('roles')->insertGetId([
                'title' => self::ROLE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $roleIds = DB::table('roles')
            ->whereIn('title', self::ADMIN_ROLES)
            ->pluck('id')
            ->push($roleId);

        // permission_role has no unique key, so only add what's missing.

        $alreadyGranted = DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->pluck('role_id');

        DB::table('permission_role')->insert(
            $roleIds->diff($alreadyGranted)->unique()->values()
                ->map(fn ($id) => ['permission_id' => $permissionId, 'role_id' => $id])
                ->all()
        );
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('title', self::PERMISSION)->value('id');
        $roleId = DB::table('roles')->where('title', self::ROLE)->value('id');

        if ($permissionId !== null) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        if ($roleId !== null) {
            DB::table('permission_role')->where('role_id', $roleId)->delete();
            DB::table('role_user')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
