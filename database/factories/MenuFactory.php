<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MenuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_menu' => fake()->randomElement([
                'Ayam Geprek',
                'Mie Pedas',
                'Es Teh Jumbo',
                'Kopi Susu',
                'Burger Special',
            ]),

            'deskripsi' => fake()->sentence(),

            'harga' => fake()->numberBetween(10000, 50000),

            'keuntungan' => fake()->numberBetween(2000, 15000),

            'gambar' => 'menus/default.png',

            'tanggal' => now()->format('Y-m-d'),

            'waktu_mulai' => '08:00',

            'waktu_selesai' => '22:00',

            'status' => fake()->randomElement([
                'available',
                'sold_out',
            ]),
        ];
    }
}
