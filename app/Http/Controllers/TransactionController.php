<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Payment;
use App\Models\Menu;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TransactionDetail;
use App\Helpers\NotificationHelper;
use App\Models\User;
use App\Services\FCMService;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Transaction::with([
            'details.menu',
            'paymentMethod',
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
                'string'
            ],
            'payment_method_id' => [
                'required',
                'exists:payment_methods,id'
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

        $currentTime = now()->timezone('Asia/Jakarta')->format('H:i:s');

        $tanggalInput = $request->input('tanggal');
        if ($tanggalInput) {
            $cleanDate = rawurldecode($tanggalInput);
            if (str_contains($cleanDate, 'T')) {
                $cleanDate = explode('T', $cleanDate)[0];
            } elseif (str_contains($cleanDate, ' ')) {
                $cleanDate = explode(' ', $cleanDate)[0];
            }
            $tanggalFix = trim($cleanDate);
        } else {
            $tanggalFix = now()->timezone('Asia/Jakarta')->format('Y-m-d');
        }

        // CHECKOUT DARI CART
        if (!empty($validated['cart_id'])) {

            $cart = Cart::with('items.menu')
                ->where('id', $validated['cart_id'])
                ->where('user_id', $request->user()->id)
                ->where('status', 'open')
                ->firstOrFail();

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
                'customer_name' => $validated['customer_name'] ?? $request->user()->name,
                'tanggal' => $tanggalFix,
                'status' => 'pending',
                'payment_method_id' => $validated['payment_method_id'],
                'total_jumlah' => $cart->total_items,
                'total_harga' => $cart->total_price,
                'total_keuntungan' => $totalKeuntungan,
            ]);

            $this->sendOrderNotification($transaction);

            foreach ($cart->items as $item) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'menu_id' => $item->menu_id,
                    'quantity' => $item->quantity,
                    'harga_satuan' => $item->price,
                    'keuntungan_satuan' => $item->menu->keuntungan ?? 0,
                    'subtotal' => $item->total_price,
                    'subtotal_keuntungan' => ($item->menu->keuntungan ?? 0) * $item->quantity,
                ]);
            }

            Payment::create([
                'user_id' =>  $request->user()->id,
                'transaction_id' => $transaction->id,
                'payment_method_id' => $validated['payment_method_id'],
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
            $menu = Menu::findOrFail($detail['menu_id']);
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
            'customer_name' => $validated['customer_name'] ?? $request->user()->name,
            'tanggal' => $tanggalFix,
            'status' => 'pending',
            'payment_method_id' => $validated['payment_method_id'],
            'total_jumlah' => $totalJumlah,
            'total_harga' => $totalHarga,
            'total_keuntungan' => $totalKeuntungan,
        ]);

        $this->sendOrderNotification($transaction);

        foreach ($validated['details'] as $detail) {
            $menu = Menu::findOrFail($detail['menu_id']);
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
            'payment_method_id' => $validated['payment_method_id'],
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
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'total_jumlah' => ['required', 'integer'],
            'total_harga' => ['required', 'numeric'],
            'total_keuntungan' => ['required', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', 'exists:menus,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            // 'items.*.harga_satuan' => ['required', 'numeric'],
            // 'items.*.keuntungan_satuan' => ['required', 'numeric'],
        ]);

        $transaction = Transaction::create([
            'user_id' => null,
            'cart_id' => null,
            'name' => $validated['name'],
            'tanggal' => $validated['tanggal'],
            'status' => 'paid',
            'payment_method_id' => $validated['payment_method_id'],
            'total_jumlah' => $validated['total_jumlah'],
            'total_harga' => $validated['total_harga'],
            'total_keuntungan' => $validated['total_keuntungan'],
        ]);


        foreach ($validated['items'] as $item) {
            $menu = Menu::findOrFail($item['menu_id']);
            TransactionDetail::create([
                'transaction_id' => $transaction->id,
                'menu_id' => $menu->id,
                'quantity' => $item['quantity'],
                'harga_satuan' => $menu->harga_jual,
                'keuntungan_satuan' => $menu->keuntungan,
                'subtotal' => $menu->harga_jual * $item['quantity'],
                'subtotal_keuntungan' => $menu->keuntungan * $item['quantity'],
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
            'tanggal'           => ['sometimes'],
            'payment_method_id' => ['sometimes', 'exists:payment_methods,id'],
            'total_jumlah'      => ['sometimes', 'integer'],
            'total_harga'       => ['sometimes', 'numeric'],
            'total_keuntungan'  => ['sometimes', 'numeric'],
            'paymentStatus'     => ['sometimes', 'string', 'in:pending,paid,cancelled,failed'],
        ]);

        $updateData = [
            'customer_name'     => $request->input('customer_name', $transaction->customer_name),
            'payment_method_id' => $request->input('payment_method_id', $transaction->payment_method_id),
            'total_jumlah'      => $request->input('total_jumlah', $transaction->total_jumlah),
            'total_harga'       => $request->input('total_harga', $transaction->total_harga),
            'total_keuntungan'  => $request->input('total_keuntungan', $transaction->total_keuntungan),
        ];

        if ($request->filled('tanggal')) {
            $cleanDate = rawurldecode($request->tanggal);
            if (str_contains($cleanDate, 'T')) {
                $cleanDate = explode('T', $cleanDate)[0];
            } elseif (str_contains($cleanDate, ' ')) {
                $cleanDate = explode(' ', $cleanDate)[0];
            }
            $updateData['tanggal'] = trim($cleanDate);
        } else {
            $updateData['tanggal'] = is_string($transaction->tanggal)
                ? explode(' ', $transaction->tanggal)[0]
                : $transaction->tanggal->format('Y-m-d');
        }

        if ($request->filled('paymentStatus')) {
            $statusBaru = $request->input('paymentStatus');
            $updateData['status'] = $statusBaru;
        }

        $transaction->update($updateData);

        if ($request->filled('paymentStatus')) {
            $statusBaru = $request->input('paymentStatus');

            $transaction->refresh();

            if ($transaction->payment) {
                $transaction->payment->update([
                    'status' => $statusBaru,
                ]);
            }
            if ($transaction->user && $statusBaru == 'paid') {
                NotificationHelper::send(
                    $transaction->user,
                    'Pesanan Selesai',
                    'Pesanan Anda telah selesai.',
                    'transaction',
                    [
                        'reference_id' => $transaction->id,
                        'transaction_id' => $transaction->id,
                        'status' => 'paid',
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Transaksi berhasil diperbarui.',
            'data' => $transaction->fresh()->load([
                'user',
                'payment',
                'details.menu'
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

    public function accept(Request $request, Transaction $transaction)
    {
        if (in_array($transaction->status, ['processing', 'ready', 'completed', 'cancelled', 'failed'])) {
            return response()->json([
                'message' => 'Transaksi tidak dapat diterima karena sudah diproses atau selesai.'
            ], 422);
        }

        $transaction->update([
            'status' => 'processing',
        ]);

        if ($transaction->user) {
            NotificationHelper::send(
                $transaction->user,
                'Pesanan Diproses',
                'Pesanan Anda sedang dibuat.',
                'transaction',
                [
                    'reference_id' => $transaction->id,
                    'transaction_id' => $transaction->id,
                    'status' => 'processing',
                ]
            );
        }

        return response()->json([
            'message' => 'Pesanan berhasil diterima.',
            'data' => $transaction->fresh()->load([
                'details.menu',
                'payment',
                'user',
            ]),
        ]);
    }

    public function ownerCancel(Transaction $transaction)
    {
        if ($transaction->status !== 'pending') {
            return response()->json([
                'message' => 'Transaksi tidak dapat dibatalkan.'
            ], 422);
        }

        $transaction->update([
            'status' => 'cancelled',
        ]);

        if ($transaction->user) {
            NotificationHelper::send(
                $transaction->user,
                'Pesanan Dibatalkan',
                'Pesanan Anda telah dibatalkan oleh penjual.',
                'transaction',
                [
                    'reference_id' => $transaction->id,
                    'transaction_id' => $transaction->id,
                    'status' => 'cancelled',
                ]
            );
        }

        if ($transaction->payment) {
            $transaction->payment->update([
                'status' => 'cancelled',
                'paid_at' => null,
            ]);
        }

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
            'data' => $transaction->fresh()->load('payment'),
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
                ['pending', 'processing'],
                true
            )
        ) {
            return response()->json([
                'message' => 'Transaksi tidak dapat dibatalkan.'
            ], 422);
        }

        $transaction->update([
            'status' => 'cancelled',
        ]);

        if ($transaction->user) {
            NotificationHelper::send(
                $transaction->user,
                'Pesanan Dibatalkan',
                'Pesanan Anda telah dibatalkan.',
                'transaction',
                [
                    'reference_id' => $transaction->id,
                    'transaction_id' => $transaction->id,
                    'status' => 'cancelled',
                ]
            );
        }

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

    public function ready(Transaction $transaction)
    {
        if ($transaction->status !== 'processing') {
            return response()->json([
                'message' => 'Pesanan belum dapat ditandai siap.'
            ], 422);
        }

        $transaction->update([
            'status' => 'ready',
        ]);

        if ($transaction->user) {
            NotificationHelper::send(
                $transaction->user,
                'Pesanan Siap',
                'Pesanan Anda siap diambil.',
                'transaction',
                [
                    'reference_id' => $transaction->id,
                    'transaction_id' => $transaction->id,
                    'status' => 'ready',
                ]
            );
        }

        return response()->json([
            'message' => 'Pesanan siap diambil.',
            'data' => $transaction->fresh()->load([
                'details.menu',
                'payment',
                'user',
            ]),
        ]);
    }

    public function paid(Transaction $transaction)
    {
        $transaction->update([
            'status' => 'completed',
        ]);

        if ($transaction->user) {
            NotificationHelper::send(
                $transaction->user,
                'Pesanan Selesai',
                'Terima kasih telah memesan di Yummy Bebz.',
                'transaction',
                [
                    'reference_id' => $transaction->id,
                    'transaction_id' => $transaction->id,
                    'status' => 'completed',
                ]
            );
        }

        return response()->json([
            'message' => 'Transaksi berhasil diperbarui menjadi dibayar.',
            'data' => $transaction->fresh()->load([
                'details.menu',
                'payment',
                'user',
            ]),
        ]);
    }

    private function sendOrderNotification(Transaction $transaction): void
    {
        $owners = User::where('role', 'owner')
            ->whereNotNull('fcm_token')
            ->get();

        foreach ($owners as $owner) {

            NotificationHelper::send(
            $owner,
            'Pesanan Baru',
            'Ada pesanan baru dari ' . $transaction->customer_name,
            'transaction', // Set type 'transaction' (bukan 'new_order')
            [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status ?? 'pending',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ]
        );
        }
    }
}
