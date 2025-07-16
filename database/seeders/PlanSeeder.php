<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $plans = [
            [
                'name' => 'Basic Plan',
                'price' => 99.99,
                'car_limite' => 5,
                'count_day' => 30,
                'is_active' => true,
            ],
            [
                'name' => 'Premium Plan',
                'price' => 199.99,
                'car_limite' => 15,
                'count_day' => 30,
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise Plan',
                'price' => 299.99,
                'car_limite' => 30,
                'count_day' => 30,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }

        $this->command->info('Plans seeded successfully!');
    }
}