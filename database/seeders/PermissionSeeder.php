<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         Permission::create(['name' => 'Create-Role', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Read-Roles', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-Role', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Delete-Role', 'guard_name' => 'sanctum']);

         Permission::create(['name' => 'Create-Permission', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Read-Permissions', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-Permission', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Delete-Permission', 'guard_name' => 'sanctum']);

         Permission::create(['name' => 'Create-Admin', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Read-Admins', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-Admin', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Delete-Admin', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Blocked-Admin', 'guard_name' => 'sanctum']);

         Permission::create(['name' => 'FeaturePlan-Create', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'FeaturePlan-Read', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'FeaturePlan-Update', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'FeaturePlan-Delete', 'guard_name' => 'sanctum']);
 Permission::create(['name' => 'FeaturePlan-Show', 'guard_name' => 'sanctum']);

         Permission::create(['name' => 'Create-Plan', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Read-Plans', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-Plan', 'guard_name' => 'sanctum']);

         Permission::create(['name' => 'Read-Subscribes', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'MarkAsPaid-Subscribe', 'guard_name' => 'sanctum']);

         Permission::create(['name' => 'Create-ModelCar', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Read-ModelCars', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-ModelCar', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Show-ModelCar', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Delete-ModelCar', 'guard_name' => 'sanctum']);

         Permission::create(['name' => 'Read-BrandCars', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Create-BrandCar', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-BrandCar', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Show-BrandCar', 'guard_name' => 'sanctum']);



         Permission::create(['name' => 'Read-UsersProfiles', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Manage-Profile', 'guard_name' => 'sanctum']);


         Permission::create(['name' => 'Read-Reviews', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Delete-Review', 'guard_name' => 'sanctum']);


         Permission::create(['name' => 'Read-DriverPrice', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-DriverPrice', 'guard_name' => 'sanctum']);




         Permission::create(['name' => 'Read-Bookings', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'ChangeStatus-Booking', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'ChangePaid-Booking', 'guard_name' => 'sanctum']);


         Permission::create(['name' => 'Update-CarFeatures', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Create-Car', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Delete-Car', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Read-Cars', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-Car', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'UpdateStatus-Car', 'guard_name' => 'sanctum']);



         Permission::create(['name' => 'Read-Stations', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Create-Station', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Show-Station', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-Station', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Delete-Station', 'guard_name' => 'sanctum']);



         Permission::create(['name' => 'Read-Representatives', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Create-Representative', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Show-Representative', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-Representative', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Delete-Representative', 'guard_name' => 'sanctum']);




         Permission::create(['name' => 'Read-Contracts', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Show-Contract', 'guard_name' => 'sanctum']);


         Permission::create(['name' => 'Read-ContractPolices', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Create-ContractPolice', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Show-ContractPolice', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-ContractPolice', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Delete-ContractPolice', 'guard_name' => 'sanctum']);



         Permission::create(['name' => 'Read-ModelYears', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Create-ModelYear', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Show-ModelYear', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Update-ModelYear', 'guard_name' => 'sanctum']);
         Permission::create(['name' => 'Delete-ModelYear', 'guard_name' => 'sanctum']);



        Permission::create(['name' => 'Read-Subscribers', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Delete-Subscribers', 'guard_name' => 'sanctum']);


        Permission::create(['name' => 'storeTestimonial_Admin', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'getTestimonialsWithFilter_Admin', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'updateTestimonial_admin', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'deleteTestimonial_Admin', 'guard_name' => 'sanctum']);


    }
}
