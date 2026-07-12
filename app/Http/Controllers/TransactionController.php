<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Payment;
use App\Models\Menu;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TransactionDetail;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Transaction::with([
            'details.menu',
            'payment',
            'rating'
        ]);

        if ($user->role === 'customer') {
            $query->where('user_id', $user->id);
        }

        $transactions = $query
            ->latest()
            ->get();

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cart_id' => [
                'nullable',
                'exists:carts,id'
            ],
            'customer_name' => [
                'nullable',
                'string'
            ],
            'tanggal' => [
                'required',
                'date'
            ],
            'metode_pembayaran' => [
                'required',
                'in:cash,midtrans'
            ],
            'details' => [
                'nullable',
                'array',
                'min:1'
            ],
            'details.*.menu_id' => [
                'required_without:cart_id',
                'exists:menus,id'
            ],
            'details.*.quantity' => [
                'required_without:cart_id',
                'integer',
                'min:1'
            ],
            'details.*.harga' => [
                'nullable',
                'numeric'
            ],
        ]);

        // Ambil waktu saat ini berdasarkan timezone server
        $currentTime = now()->format('H:i:s');

        // ==========================================
        // ALUR 1: CHECKOUT DARI CART
        // ==========================================
        if (!empty($validated['cart_id'])) {

            $cart = Cart::with('items.menu')
                ->where('id', $validated['cart_id'])
                ->where('user_id', $request->user()->id)
                ->where('status', 'open')
                ->firstOrFail();

            // 🛡️ VALIDASI JADWAL MENU DI DALAM CART
            foreach ($cart->items as $item) {
                $menu = $item->menu;
                if ($menu && $menu->waktu_mulai && $menu->waktu_selesai) {
                    if ($currentTime < $menu->waktu_mulai || $currentTime > $menu->waktu_selesai) {
                        return response()->json([
                            'message' => "Menu '{$menu->nama_menu}' saat ini sedang tidak tersedia atau di luar jam operasional.",
                            'errors' => ['menu' => ["Menu '{$menu->nama_menu}' hanya tersedia pada jam {$menu->waktu_mulai} - {$menu->waktu_selesai}."]]
                        ], 422);
                    }
                }
            }

            $totalKeuntungan = 0;

            foreach ($cart->items as $item) {
                $totalKeuntungan +=
                    ($item->menu->keuntungan ?? 0)
                    * $item->quantity;
            }

            $transaction = Transaction::create([
                'user_id' => $request->user()->id,
                'cart_id' => $cart->id,
                'customer_name' => $validated['customer_name']  ?? $request->user()->name,
                'tanggal' => $validated['tanggal'],
                'status' => 'pending',
                'metode_pembayaran' => $validated['metode_pembayaran'],
                'total_jumlah' => $cart->total_items,
                'total_harga' => $cart->total_price,
                'total_keuntungan' => $totalKeuntungan,
            ]);

            foreach ($cart->items as $item) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'menu_id' => $item->menu_id,
                    'quantity' => $item->quantity,
                    'harga_satuan' => $item->price,
                    'keuntungan_satuan' => $item->menu->keuntungan ?? 0,
                    'subtotal' => $item->total_price,
                    'subtotal_keuntungan' => ($item->menu->keuntungan ?? 0)  * $item->quantity,
                ]);
            }

            Payment::create([
                'user_id' =>  $request->user()->id,
                'transaction_id' => $transaction->id,
                'metode_pembayaran' =>  $validated['metode_pembayaran'],
                'amount' => $cart->total_price,
                'status' => 'pending',
            ]);

            $cart->update([
                'status' => 'checked_out'
            ]);

            return response()->json([
                'message' => 'Transaksi berhasil dibuat.',
                'data' => $transaction->load([
                    'payment',
                    'details.menu',
                    'user'
                ]),
            ], 201);
        }

        // ==========================================
        // ALUR 2: PEMBELIAN LANGSUNG ("PESAN SEKARANG")
        // ==========================================

        // 🛡️ VALIDASI JADWAL MENU SEBELUM PROSES HITUNG
        foreach ($validated['details'] as $detail) {
            $menu = Menu::findOrFail($detail['menu_id']);

            if ($menu->waktu_mulai && $menu->waktu_selesai) {
                if ($currentTime < $menu->waktu_mulai || $currentTime > $menu->waktu_selesai) {
                    return response()->json([
                        'message' => "Menu '{$menu->nama_menu}' saat ini sedang tidak tersedia atau di luar jam operasional.",
                        'errors' => ['menu' => ["Menu '{$menu->nama_menu}' hanya tersedia pada jam {$menu->waktu_mulai} - {$menu->waktu_selesai}."]]
                    ], 422);
                }
            }
        }

        $totalJumlah = 0;
        $totalHarga = 0;
        $totalKeuntungan = 0;

        foreach ($validated['details'] as $detail) {
            $menu = Menu::findOrFail(
                $detail['menu_id']
            );
            $qty = $detail['quantity'];
            $subtotal = $menu->harga_jual * $qty;
            $keuntungan = $menu->keuntungan * $qty;
            $totalJumlah += $qty;
            $totalHarga += $subtotal;
            $totalKeuntungan += $keuntungan;
        }

        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'cart_id' => null,
            'customer_name' => $validated['customer_name']  ?? $request->user()->name,
            'tanggal' => $validated['tanggal'],
            'status' => 'pending',
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'total_jumlah' => $totalJumlah,
            'total_harga' => $totalHarga,
            'total_keuntungan' => $totalKeuntungan,
        ]);

        foreach ($validated['details'] as $detail) {
            $menu = Menu::findOrFail(
                $detail['menu_id']
            );

            $qty = $detail['quantity'];
            TransactionDetail::create([
                'transaction_id' => $transaction->id,
                'menu_id' => $menu->id,
                'quantity' => $qty,
                'harga_satuan' => $menu->harga_jual,
                'keuntungan_satuan' => $menu->keuntungan,
                'subtotal' => $menu->harga_jual * $qty,
                'subtotal_keuntungan' => $menu->keuntungan * $qty,
            ]);
        }

        Payment::create([
            'user_id' => $request->user()->id,
            'transaction_id' => $transaction->id,
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'amount' => $totalHarga,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Transaksi berhasil dibuat.',
            'data' => $transaction->load([
                'payment',
                'details.menu',
                'user'
            ]),
        ], 201);
    }

    public function storeOffline(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
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
            'name' => $validated['name'],
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
                'subtotal' => $item['harga_satuan'] * $item['quantity'],
                'subtotal_keuntungan' => $item['keuntungan_satuan'] * $item['quantity'],
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
            'customer_name'     => ['sometimes', 'string', 'max:255'],
            'tanggal'           => ['sometimes', 'date'],
            'metode_pembayaran' => ['sometimes', 'string', 'max:100'],
            'total_jumlah'      => ['sometimes', 'integer'],
            'total_harga'       => ['sometimes', 'numeric'],
            'total_keuntungan'  => ['sometimes', 'numeric'],
            'status_pembayaran' => ['sometimes', 'string', 'in:pending,paid,cancelled,failed'],
        ]);

        $transaction->update([
            'customer_name'     => $request->input('customer_name', $transaction->customer_name),
            'tanggal'           => $request->input('tanggal', $transaction->tanggal),
            'metode_pembayaran' => $request->input('metode_pembayaran', $transaction->metode_pembayaran),
            'total_jumlah'      => $request->input('total_jumlah', $transaction->total_jumlah),
            'total_harga'       => $request->input('total_harga', $transaction->total_harga),
            'total_keuntungan'  => $request->input('total_keuntungan', $transaction->total_keuntungan),
        ]);

        if ($request->has('status_pembayaran')) {
            if ($transaction->payment) {
                $transaction->payment->update([
                    'status' => $request->status_pembayaran,
                ]);
            }
        }

        return response()->json([
            'message' => 'Transaksi berhasil diperbarui.',
            'data' => $transaction->fresh()->load([
                'user',
                'payment',
                'details.menu' // Sesuaikan jika relasinya bernama 'details' atau 'cart.items.menu'
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
