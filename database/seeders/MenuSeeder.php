<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            [
                'nama_menu' => 'Bye Bye Bye',
                'category' => 'sing_a_song',
                'deskripsi' => 'Set cheese burger sandwich beef with creamy cheese sauce and mozzarella + caramelize onion + mushroom, kentang goreng yang ngejuuu banget!',
                'harga_modal' => 10000,
                'harga_jual' => 15000,
            ],
            [
                'nama_menu' => 'Cheese Burger Sandwich Yum',
                'category' => 'western',
                'deskripsi' => 'Roti isi daging burger yang dicincang, caramelize onion, perkejuan (cheddar, mozzarella, cheese sauce yummy).',
                'harga_modal' => 15000,
                'harga_jual' => 25000,
            ],
            [
                'nama_menu' => 'Hot Spicy Noodeng Yum',
                'category' => 'noodle',
                'deskripsi' => 'Perpaduan mie dan odeng (jajanan khas Korea) dengan saus korean spicy dan taburan wijen + nori flakes.',
                'harga_modal' => 12000,
                'harga_jual' => 20000,
            ],
            [
                'nama_menu' => 'Choco Yummy Toast',
                'category' => 'sweet_toast',
                'deskripsi' => 'Roti tawar yang dipanggang dengan olesan cokelat dan taburan keju parut.',
                'harga_modal' => 8000,
                'harga_jual' => 18000,
            ],
            [
                'nama_menu' => 'Cheese Burger',
                'category' => 'western',
                'deskripsi' => 'Roti isi daging burger yang dicincang, caramelize onion, perkejuan (cheddar, mozzarella, cheese sauce yummy).',
                'harga_modal' => 15000,
                'harga_jual' => 22000,
            ],
            [
                'nama_menu' => 'Blackpapper Noodles',
                'category' => 'noodle',
                'deskripsi' => 'Mie dengan saus blackpepper yang pedas dan gurih, ditambah irisan daging sapi dan sayuran.',
                'harga_modal' => 20000,
                'harga_jual' => 30000,
            ],
            [
                'nama_menu' => 'Quesadilla Yum',
                'category' => 'foody',
                'deskripsi' => 'Tortilla isi keju leleh, daging cincang, dan sayuran segar.',
                'harga_modal' => 18000,
                'harga_jual' => 28000,
            ],
            [
                'nama_menu' => 'Dry Ramen Yum',
                'category' => 'noodle',
                'deskripsi' => 'Mie kering dengan saus gurih dan topping daging sapi.',
                'harga_modal' => 22000,
                'harga_jual' => 32000,
            ],
            [
                'nama_menu' => 'Unforgiven',
                'category' => 'sing_a_song',
                'deskripsi' => 'Menu spesial dengan cita rasa khas Yummy Bebz.',
                'harga_modal' => 15000,
                'harga_jual' => 18000,
            ],
            [
                'nama_menu' => 'Americano',
                'category' => 'beverage',
                'deskripsi' => 'Kopi hitam dengan cita rasa yang kaya dan aroma yang kuat.',
                'harga_modal' => 10000,
                'harga_jual' => 12000,
            ],
            [
                'nama_menu' => 'Cappuccino',
                'category' => 'beverage',
                'deskripsi' => 'Espresso dengan susu yang creamy dan foam lembut.',
                'harga_modal' => 12000,
                'harga_jual' => 15000,
            ],
            [
                'nama_menu' => 'Iced Tea',
                'category' => 'beverage',
                'deskripsi' => 'Teh dingin yang menyegarkan.',
                'harga_modal' => 8000,
                'harga_jual' => 10000,
            ],
            [
                'nama_menu' => 'Ices Lychee Tea',
                'category' => 'beverage',
                'deskripsi' => 'Teh dingin dengan rasa manis dan aroma buah leci.',
                'harga_modal' => 9000,
                'harga_jual' => 12000,
            ],
            [
                'nama_menu' => 'Iced Lemon Tea',
                'category' => 'beverage',
                'deskripsi' => 'Teh lemon dingin yang segar.',
                'harga_modal' => 7000,
                'harga_jual' => 11000,
            ],
        ];

        foreach ($menus as $menu) {
            Menu::create([
                'nama_menu' => $menu['nama_menu'],
                'category' => $menu['category'],
                'deskripsi' => $menu['deskripsi'],
                'harga_modal' => $menu['harga_modal'],
                'harga_jual' => $menu['harga_jual'],
                'keuntungan' => $menu['harga_jual'] - $menu['harga_modal'],
                'gambar' => 'menus/default.png',
                'tanggal' => now()->format('Y-m-d'),
                'waktu_mulai' => '08:00',
                'waktu_selesai' => '22:00',
                'status' => 'available',
            ]);
        }
    }
}
