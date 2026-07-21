<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('transaction_id');
            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions')
                ->cascadeOnDelete();

            $table->unsignedInteger('menu_id');
            $table->foreign('menu_id')
                ->references('id')
                ->on('menus')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            $table->decimal('harga_satuan', 12, 2)->default(0);

            $table->decimal('keuntungan_satuan', 12, 2)->default(0);

            $table->decimal('subtotal', 12, 2)->default(0);

            $table->decimal('subtotal_keuntungan', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
    }
};
