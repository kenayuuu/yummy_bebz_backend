<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id,

            'cart_id' => Cart::inRandomOrder()->first()?->id,

            'name' => fake()->name(),

            'tanggal' => now(),

            'status' => fake()->randomElement([
                'pending',
                'paid',
                'cancelled',
            ]),

            'metode_pembayaran' => fake()->randomElement([
                'cash',
                'qris',
                'transfer',
            ]),

            'total_jumlah' => fake()->numberBetween(1, 10),

            'total_harga' => fake()->numberBetween(15000, 150000),
        ];
    }
}
