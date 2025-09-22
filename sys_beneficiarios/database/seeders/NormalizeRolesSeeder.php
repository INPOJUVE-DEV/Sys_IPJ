<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class NormalizeRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure canonical role exists
        $canonical = Role::firstOrCreate(['name' => 'encargado_360']);

        // Merge from possible legacy 'Encargado360'
        $legacy = Role::where('name', 'Encargado360')->first();
        if ($legacy) {
            // Merge permissions
            $permNames = $legacy->permissions()->pluck('name')->all();
            if ($permNames) {
                foreach ($permNames as $p) {
                    Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
                }
                $canonical->givePermissionTo($permNames);
            }
            // Reassign users to canonical role
            $userIds = DB::table('model_has_roles')
                ->where('role_id', $legacy->id)
                ->where('model_type', \App\Models\User::class)
                ->pluck('model_id');
            foreach ($userIds as $uid) {
                $user = \App\Models\User::find($uid);
                if ($user) {
                    $user->syncRoles(array_unique(array_merge($user->getRoleNames()->toArray(), ['encargado_360'])));
                }
            }
            // Delete legacy role
            $legacy->delete();
        }

        // Remove generic 'encargado' role: reassign its users to 'encargado_360'
        $generic = Role::where('name', 'encargado')->first();
        if ($generic) {
            $userIds = DB::table('model_has_roles')
                ->where('role_id', $generic->id)
                ->where('model_type', \App\Models\User::class)
                ->pluck('model_id');
            foreach ($userIds as $uid) {
                $user = \App\Models\User::find($uid);
                if ($user) {
                    $user->syncRoles(array_values(array_diff($user->getRoleNames()->toArray(), ['encargado'])));
                    if (! $user->hasRole('encargado_360')) {
                        $user->assignRole('encargado_360');
                    }
                }
            }
            $generic->delete();
        }
    }
}

