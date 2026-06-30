<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Factories\TransactionDetailFactory;

class TransactionDetailSeeder extends Seeder
{
    public function run(): void
    {
        TransactionDetailFactory::factory()->count(10)->create();
    }
}
