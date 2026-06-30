<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionDetailFactory extends Factory
{
    public function definition(): array
    {
        $menu = Menu::inRandomOrder()->first();

        $quantity = fake()->numberBetween(1, 5);

        $hargaSatuan = $menu?->harga_jual ?? fake()->numberBetween(10000, 50000);

        $keuntunganSatuan = $menu?->keuntungan ?? fake()->numberBetween(3000, 10000);

        return [
            'transaction_id' => Transaction::inRandomOrder()->first()?->id,

            'menu_id' => $menu?->id,

            'quantity' => $quantity,

            'harga_satuan' => $hargaSatuan,

            'keuntungan_satuan' => $keuntunganSatuan,

            'subtotal' => $hargaSatuan * $quantity,

            'subtotal_keuntungan' => $keuntunganSatuan * $quantity,
        ];
    }
}
