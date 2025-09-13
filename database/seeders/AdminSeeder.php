<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        User::create([
            'name' => 'م.محمد عادل البحيصي',
            'email' => 'mohammadalbohisi@gmail.com',
            'mobile' => '0598595579',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', //password
        ])->syncRoles(['Super Admin']);
    }

    public function givPermission()
    {
        $admin = User::find(1);
        $Permissions = Permission::all();
        $allPermission = [];
        foreach ($Permissions as $Permission) {
            array_push($allPermission, $Permission->name);
        }
        $admin->givePermissionTo($allPermission);
    }
}
