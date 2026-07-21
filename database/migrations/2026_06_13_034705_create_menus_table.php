<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('category', [
                'western',
                'sing_a_song',
                'sweet_toast',
                'foody',
                'additional',
                'noodle',
                'beverage',
            ]);
            $table->string('nama_menu', 50);
            $table->text('deskripsi')->nullable();
            $table->decimal('harga_modal', 12, 2);
            $table->decimal('harga_jual', 12, 2);
            $table->decimal('keuntungan', 12, 2)->default(0);
            $table->string('gambar', 100)->nullable();
            $table->date('tanggal')->nullable();
            $table->time('waktu_mulai')->nullable();
            $table->time('waktu_selesai')->nullable();
            $table->string('status', 20)->default('tersedia');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
