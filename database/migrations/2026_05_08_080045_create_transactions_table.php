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
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cart_id')->nullable();
            $table->string('name')->nullable();
            $table->date('tanggal');
            $table->enum('status', ['pending', 'paid', 'cancelled', 'completed'])->default('pending');
            $table->string('metode_pembayaran')->nullable();
            $table->unsignedInteger('total_jumlah')->default(0);
            $table->decimal('total_harga', 12, 2)->default(0);
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
