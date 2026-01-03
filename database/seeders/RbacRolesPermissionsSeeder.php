<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RbacRolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Define roles
        $roles = [
            'superadmin',
            'organization_admin',
            'hr_manager',
            'recruiter',
            'payroll_manager',
            'team_manager',
            'employee',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Define some example permissions (customize for your modules)
        $permissions = [
            'employees.view',
            'employees.create',
            'employees.edit',
            'employees.delete',
            'recruitment.manage',
            'attendance.manage',
            'payroll.run',
            'timesheet.manage',
            'roster.manage',
            'performance.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Assign permissions to roles (example)
        Role::where('name', 'hr_manager')->first()->givePermissionTo([
            'employees.view','employees.create','employees.edit','recruitment.manage','attendance.manage','timesheet.manage','roster.manage','performance.manage'
        ]);

        Role::where('name', 'payroll_manager')->first()->givePermissionTo([
            'payroll.run'
        ]);

        Role::where('name', 'organization_admin')->first()->givePermissionTo(Permission::all());
        Role::where('name', 'superadmin')->first()->givePermissionTo(Permission::all());
    }
}
