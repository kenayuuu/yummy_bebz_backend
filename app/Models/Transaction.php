<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'cart_id',
        'customer_name',
        'tanggal',
        'status',
        'metode_pembayaran',
        'total_jumlah',
        'total_harga',
        'total_keuntungan',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'total_harga' => 'decimal:2',
            'status' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
    public function details()
    {
        return $this->hasMany(TransactionDetail::class, 'transaction_id');
    }
    public function chats()
    {
        return $this->hasMany(Chat::class);
    }
    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class, 'transaction_id');
    }
}
