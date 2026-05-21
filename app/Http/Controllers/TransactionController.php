<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['user', 'cart.items.menu', 'payment'])->latest();

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
            ->when(isset($validated['cart_id']), fn($query) => $query->where('id', $validated['cart_id']))
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->firstOrFail();

        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'cart_id' => $cart->id,
            'name' => $request->user()->name,
            'tanggal' => $validated['tanggal'],
            'status' => 'pending',
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'total_jumlah' => $cart->total_items,
            'total_harga' => $cart->total_price,
        ]);

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'transaction_id' => $transaction->id,
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'amount' => $cart->total_price,
            'status' => 'pending',
        ]);

        $cart->update(['status' => 'checked_out']);

        return response()->json([
            'message' => 'Transaksi berhasil dibuat dari keranjang.',
            'data' => $transaction->load(['cart.items.menu', 'payment']),
            'payment' => $payment,
        ], 201);
    }

    public function show(Request $request, Transaction $transaction)
    {
        if ($request->user()->role === 'customer' && $transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Data transaksi tidak ditemukan.'], 404);
        }

        return response()->json($transaction->load(['cart.items.menu', 'payment', 'user']));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'tanggal' => ['sometimes', 'date'],
            'metode_pembayaran' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'in:pending,paid,cancelled,completed'],
        ]);

        $transaction->update($validated);

        return response()->json([
            'message' => 'Transaksi berhasil diperbarui.',
            'data' => $transaction->fresh()->load(['cart.items.menu', 'payment']),
        ]);
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();

        return response()->json([
            'message' => 'Transaksi berhasil dihapus.',
        ]);
    }

    public function cancel(Request $request, Transaction $transaction)
    {
        if ($request->user()->role === 'customer' && $transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        if (! in_array($transaction->status, ['pending', 'paid'], true)) {
            return response()->json(['message' => 'Transaksi tidak dapat dibatalkan.'], 422);
        }

        $transaction->update(['status' => 'cancelled']);

        if ($transaction->payment) {
            $transaction->payment->update(['status' => 'cancelled']);
        }

        return response()->json([
            'message' => 'Transaksi berhasil dibatalkan.',
            'data' => $transaction->fresh()->load(['payment']),
        ]);
    }
}
