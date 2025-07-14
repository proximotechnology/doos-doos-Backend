<?php

namespace Database\Seeders;

use App\Models\ModelCars;
use Illuminate\Database\Seeder;

class ModelCarsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $models = [
            ['name' => 'Toyota Camry'],
            ['name' => 'Honda Accord'],
            ['name' => 'Nissan Altima'],
            ['name' => 'BMW 3 Series'],
            ['name' => 'Mercedes C-Class'],
            ['name' => 'Hyundai Sonata'],
            ['name' => 'Kia Optima'],
            ['name' => 'Ford Mustang'],
            ['name' => 'Chevrolet Malibu'],
            ['name' => 'Audi A4'],
        ];

        foreach ($models as $model) {
            ModelCars::create($model);
        }

        $this->command->info('Car models seeded successfully!');
    }
}