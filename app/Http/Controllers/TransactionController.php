<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TransactionDetail;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with([
            'user',
            'payment',
            'cart.items.menu',
            'details.menu',
        ])->latest();

        if ($request->user()->role === 'customer') {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cart_id' => ['sometimes', 'exists:carts,id'],
            'tanggal' => ['required', 'date'],
            'metode_pembayaran' => ['required', 'string', 'max:100'],
        ]);

        $cart = Cart::with('items.menu')
            ->when(
                isset($validated['cart_id']),
                fn($query) => $query->where('id', $validated['cart_id'])
            )
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->firstOrFail();

        $totalKeuntungan = $cart->items->sum(function ($item) {
            return (($item->menu->keuntungan ?? 0) * $item->quantity);
        });

        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'cart_id' => $cart->id,
            'name' => $request->user()->name,
            'tanggal' => $validated['tanggal'],
            'status' => 'pending',
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'total_jumlah' => $cart->total_items,
            'total_harga' => $cart->total_price,
            'total_keuntungan' => $totalKeuntungan,
        ]);

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'transaction_id' => $transaction->id,
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'amount' => $cart->total_price,
            'status' => 'pending',
        ]);

        $cart->update([
            'status' => 'checked_out'
        ]);

        return response()->json([
            'message' => 'Transaksi berhasil dibuat.',
            'data' => $transaction->load([
                'cart.items.menu',
                'details.menu',
                'payment',
                'user'
            ]),
            'payment' => $payment,
        ], 201);
    }

    //Transaksi Offline (Owner)
    public function storeOffline(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'metode_pembayaran' => ['required', 'string'],

            'total_jumlah' => ['required', 'integer'],
            'total_harga' => ['required', 'numeric'],
            'total_keuntungan' => ['required', 'numeric'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.menu_id' => ['required', 'exists:menus,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.harga_satuan' => ['required', 'numeric'],
            'items.*.keuntungan_satuan' => ['required', 'numeric'],
        ]);

        $transaction = Transaction::create([
            'user_id' => null,
            'cart_id' => null,
            'name' => $validated['customer_name'],
            'tanggal' => $validated['tanggal'],
            'status' => 'paid',
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'total_jumlah' => $validated['total_jumlah'],
            'total_harga' => $validated['total_harga'],
            'total_keuntungan' => $validated['total_keuntungan'],
        ]);

        foreach ($validated['items'] as $item) {

            TransactionDetail::create([
                'transaction_id' => $transaction->id,

                'menu_id' => $item['menu_id'],

                'quantity' => $item['quantity'],

                'harga_satuan' => $item['harga_satuan'],

                'keuntungan_satuan' => $item['keuntungan_satuan'],

                'subtotal' =>
                $item['harga_satuan'] *
                    $item['quantity'],

                'subtotal_keuntungan' =>
                $item['keuntungan_satuan'] *
                    $item['quantity'],
            ]);
        }

        return response()->json([
            'message' => 'Transaksi offline berhasil ditambahkan.',

            'data' => $transaction->load([
                'details.menu',
            ]),
        ], 201);
    }

    public function show(Request $request, Transaction $transaction)
    {
        if (
            $request->user()->role === 'customer' &&
            $transaction->user_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Data transaksi tidak ditemukan.'
            ], 404);
        }

        return response()->json(
            $transaction->load([
                'user',
                'payment',
                'cart.items.menu'
            ])
        );
    }

    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'tanggal' => ['sometimes', 'date'],
            'metode_pembayaran' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'in:pending,paid,cancelled,completed'],
        ]);

        if (
            $transaction->payment &&
            $transaction->payment->status === 'paid'
        ) {
            return response()->json([
                'message' => 'Transaksi yang sudah dibayar tidak dapat diubah.'
            ], 422);
        }

        $transaction->update($validated);

        return response()->json([
            'message' => 'Transaksi berhasil diperbarui.',
            'data' => $transaction->fresh()->load([
                'user',
                'payment',
                'cart.items.menu'
            ]),
        ]);
    }

    public function destroy(Transaction $transaction)
    {
        if (
            $transaction->payment &&
            $transaction->payment->status === 'paid'
        ) {
            return response()->json([
                'message' => 'Transaksi yang sudah dibayar tidak dapat dihapus.'
            ], 422);
        }

        if ($transaction->payment) {
            $transaction->payment->delete();
        }

        $transaction->delete();

        return response()->json([
            'message' => 'Transaksi berhasil dihapus.'
        ]);
    }

    public function cancel(Request $request, Transaction $transaction)
    {
        if (
            $request->user()->role === 'customer' &&
            $transaction->user_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan.'
            ], 404);
        }

        if (
            !in_array(
                $transaction->status,
                ['pending', 'paid'],
                true
            )
        ) {
            return response()->json([
                'message' => 'Transaksi tidak dapat dibatalkan.'
            ], 422);
        }

        $transaction->update([
            'status' => 'cancelled'
        ]);

        if ($transaction->payment) {
            $transaction->payment->update([
                'status' => 'cancelled',
                'paid_at' => null,
            ]);
        }

        return response()->json([
            'message' => 'Transaksi berhasil dibatalkan.',
            'data' => $transaction->fresh()->load('payment'),
        ]);
    }
}
