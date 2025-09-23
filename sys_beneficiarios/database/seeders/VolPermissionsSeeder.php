<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class VolPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'vol.groups.manage',
            'vol.enrollments.manage',
            'vol.reports.view',
            'vol.groups.view',
            'vol.sites.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roleAssignments = [
            'encargadoBienestar' => [
                'vol.groups.manage',
                'vol.sites.manage',
                'vol.reports.view',
            ],
            'encargado_JAV' => [
                'vol.enrollments.manage',
                'vol.groups.view',
                'vol.reports.view',
            ],
            'admin' => $permissions,
        ];

        $roleAliases = [
            'encargadoBienestar' => ['encargadoBienestar', 'encargado_bienestar'],
            'encargado_JAV' => ['encargado_JAV', 'encargado_jav'],
            'admin' => ['admin'],
        ];

        foreach ($roleAssignments as $key => $permissionList) {
            $targets = $roleAliases[$key] ?? [$key];
            foreach ($targets as $roleName) {
                $role = Role::firstOrCreate(['name' => $roleName]);
                $role->givePermissionTo($permissionList);
            }
        }
    }
}
