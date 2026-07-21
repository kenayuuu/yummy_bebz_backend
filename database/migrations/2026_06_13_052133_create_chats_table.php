<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('sender_id');
            $table->foreign('sender_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->unsignedInteger('receiver_id');
            $table->foreign('receiver_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->text('message');

            $table->boolean('is_read')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
