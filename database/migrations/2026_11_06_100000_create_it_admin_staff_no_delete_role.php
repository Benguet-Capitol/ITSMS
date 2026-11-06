<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "It Admin Staff (No Delete)": the same access as It Admin Staff without
 * any *.delete permission, for staff who may add and edit records in every
 * module but never delete them (first used for a new hire, 2026-11-06).
 *
 * Permissions are matched by title, not id -- local and production ids
 * differ. Safe to re-run: the role is created once and only missing
 * permissions are added (permission_role has no unique key, so duplicates
 * have to be avoided here). Skipped when It Admin Staff doesn't exist
 * (e.g. an empty test database), rather than creating an empty role.
 *
 * Only the permissions It Admin Staff has today are copied; a permission
 * given to It Admin Staff later must be given to this role too.
 */
return new class extends Migration
{
    private const ROLE = 'It Admin Staff (No Delete)';

    private const SOURCE_ROLE = 'It Admin Staff';

    public function up(): void
    {
        $sourceRoleId = DB::table('roles')->where('title', self::SOURCE_ROLE)->value('id');

        if ($sourceRoleId === null) {
            return;
        }

        $roleId = DB::table('roles')->where('title', self::ROLE)->value('id')
            ?? DB::table('roles')->insertGetId([
                'title' => self::ROLE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $permissionIds = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permission_role.role_id', $sourceRoleId)
            ->where('permissions.title', 'not like', '%.delete')
            ->distinct()
            ->pluck('permissions.id');

        $existing = DB::table('permission_role')
            ->where('role_id', $roleId)
            ->pluck('permission_id')
            ->all();

        $missing = $permissionIds->diff($existing)->values();

        DB::table('permission_role')->insert(
            $missing->map(fn ($permissionId) => [
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ])->all()
        );
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('title', self::ROLE)->value('id');

        if ($roleId === null) {
            return;
        }

        DB::table('permission_role')->where('role_id', $roleId)->delete();
        DB::table('role_user')->where('role_id', $roleId)->delete();
        DB::table('roles')->where('id', $roleId)->delete();
    }
};
