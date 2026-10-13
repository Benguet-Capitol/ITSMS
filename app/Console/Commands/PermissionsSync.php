<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 *
 */
class PermissionsSync extends Command
{
    protected $signature = 'permissions:sync {--dry-run : Show what would change without writing anything}';

    protected $description = 'Add the reporting and request-module permissions to an existing database and grant them to their roles (idempotent)';

    /** Roles every permission below is granted to (mirrors PermissionRoleTableSeeder). */
    private const ROLES = ['Admin', 'It Admin Staff', 'It Technical'];

    /** Permissions added after the production snapshot (mirrors PermissionSeeder ids 106+). */
    private const PERMISSIONS = [
        'tickets.report',
        'requests.other_it_services.report',

        'requests.cctv_footage.view', 'requests.cctv_footage.create', 'requests.cctv_footage.update',
        'requests.cctv_footage.print', 'requests.cctv_footage.delete',

        'requests.documentation.view', 'requests.documentation.create', 'requests.documentation.update',
        'requests.documentation.print', 'requests.documentation.delete',

        'requests.network_assistance.view', 'requests.network_assistance.create', 'requests.network_assistance.update',
        'requests.network_assistance.print', 'requests.network_assistance.delete',

        'requests.design.view', 'requests.design.create', 'requests.design.update',
        'requests.design.print', 'requests.design.delete',

        'requests.dts_user.view', 'requests.dts_user.create', 'requests.dts_user.update',
        'requests.dts_user.print', 'requests.dts_user.delete',

        'requests.is_change.view', 'requests.is_change.create', 'requests.is_change.update',
        'requests.is_change.print', 'requests.is_change.delete',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $roles = Role::query()->whereIn('title', self::ROLES)->get()->keyBy('title');
        $missingRoles = array_diff(self::ROLES, $roles->keys()->all());

        foreach ($missingRoles as $title) {
            $this->warn("Role \"{$title}\" not found -- its grants are skipped.");
        }

        $createdPermissions = 0;
        $createdGrants = 0;

        $apply = function () use ($roles, $dryRun, &$createdPermissions, &$createdGrants) {
            foreach (self::PERMISSIONS as $title) {
                $permission = Permission::query()->where('title', $title)->first();

                if (! $permission) {
                    $createdPermissions++;
                    $this->line("  + permission {$title}");

                    if ($dryRun) {
                        // Nothing to attach grants to yet; every role would be granted.
                        foreach ($roles as $role) {
                            $createdGrants++;
                            $this->line("    + grant to {$role->title}");
                        }

                        continue;
                    }

                    $permission = Permission::create(['title' => $title]);
                }

                foreach ($roles as $role) {
                    // permission_role has no unique key, so check before inserting.
                    $granted = DB::table('permission_role')
                        ->where('permission_id', $permission->id)
                        ->where('role_id', $role->id)
                        ->exists();

                    if ($granted) {
                        continue;
                    }

                    $createdGrants++;
                    $this->line("    + grant {$title} to {$role->title}");

                    if (! $dryRun) {
                        $role->permissions()->attach($permission->id);
                    }
                }
            }
        };

        $dryRun ? $apply() : DB::transaction($apply);

        $verb = $dryRun ? 'Would add' : 'Added';
        $this->info("{$verb} {$createdPermissions} permission(s) and {$createdGrants} role grant(s).");

        if ($createdPermissions === 0 && $createdGrants === 0) {
            $this->info('Everything is already in place.');
        } elseif (! $dryRun) {
            $this->comment('Signed-in users pick up new permissions on their next request; refresh the browser to see new menu items.');
        }

        return self::SUCCESS;
    }
}
