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
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->unsignedInteger('cart_id')->nullable();
            $table->foreign('cart_id')
                ->references('id')
                ->on('carts')
                ->nullOnDelete();
            $table->string('name', 20)->nullable();
            $table->date('tanggal');
            $table->enum('status', [
                'pending',
                'processing',
                'ready',
                'paid',
                'cancelled',
                'completed'
            ])->default('pending');
            $table->unsignedInteger('payment_method_id')->nullable();
            $table->foreign('payment_method_id')
                ->references('id')
                ->on('payment_methods')
                ->nullOnDelete(); 
            $table->unsignedInteger('total_jumlah')->default(0);
            $table->decimal('total_harga', 12, 2)->default(0);
            $table->decimal('total_keuntungan', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
