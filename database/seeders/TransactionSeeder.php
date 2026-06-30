<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transaction;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        Transaction::factory(20)
            ->create()
            ->each(function ($transaction) {

                $menus = \App\Models\Menu::inRandomOrder()
                    ->take(rand(2, 5))
                    ->get();

                foreach ($menus as $menu) {

                    $qty = rand(1, 3);

                    \App\Models\TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'menu_id' => $menu->id,
                        'quantity' => $qty,
                        'harga_satuan' => $menu->harga_jual,
                        'keuntungan_satuan' => $menu->keuntungan,
                        'subtotal' => $menu->harga_jual * $qty,
                        'subtotal_keuntungan' => $menu->keuntungan * $qty,
                    ]);
                }
            });
    }
}
