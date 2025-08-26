<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ContractItem;

class ContractItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $contractItems = [
            ['item' => 'The renter acknowledges full payment of the agreed amount on time.'],
            ['item' => 'The renter is obligated to return the vehicle at the agreed time and location.'],
            ['item' => 'The renter is held responsible for any damages that occur to the vehicle during the rental period.'],
            ['item' => 'The owner is committed to delivering the vehicle in the agreed technical and visual condition.'],
            ['item' => 'It is strictly prohibited to use the vehicle for illegal purposes or racing.'],
            ['item' => 'The renter shall pay a late fee in case of delay in returning the vehicle.'],
            ['item' => 'Both parties acknowledge that the contract is subject to the laws and regulations of the country.'],
            ['item' => 'The owner is responsible for providing comprehensive insurance for the vehicle throughout the contract duration.'],
            ['item' => 'Smoking or transporting animals inside the vehicle is prohibited unless prior approval is obtained.'],
            ['item' => 'The renter shall bear the full cost of repairs in case of damages resulting from negligence.'],
        ];


        foreach ($contractItems as $item) {
            ContractItem::create($item);
        }

        // أو باستخدام DB facade:
        // DB::table('contract_items')->insert($contractItems);
    }
}
