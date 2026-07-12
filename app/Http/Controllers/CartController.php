<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Menu;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = Cart::with(['items.menu'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        return response()->json(
            $cart?->load('items.menu')
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'menu_id' => ['required', 'exists:menus,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $menu = Menu::find($validated['menu_id']);

        if (!$menu) {
            return response()->json(['message' => 'Menu tidak ditemukan'], 404);
        }

        $price = $menu->harga_jual;

        $cart = Cart::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'open'],
            ['total_items' => 0, 'subtotal' => 0, 'total_price' => 0]
        );

        $item = CartItem::where('cart_id', $cart->id)
            ->where('menu_id', $menu->id)
            ->first();

        if ($item) {
            $item->quantity += $validated['quantity'];
            $item->total_price = $price * $item->quantity;
            $item->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'menu_id' => $menu->id,
                'price' => $price,
                'quantity' => $validated['quantity'],
                'total_price' => $price * $validated['quantity'],
            ]);
        }

        $this->recalculateCart($cart);

        return response()->json([
            'message' => 'Menu berhasil ditambahkan ke keranjang',
            'cart' => $cart->load('items.menu'),
        ], 201);
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $this->authorizeCartItem($request, $cartItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cartItem->update([
            'quantity' => $validated['quantity'],
            'total_price' => $cartItem->price * $validated['quantity'],
        ]);

        $this->recalculateCart($cartItem->cart);

        return response()->json([
            'message' => 'Jumlah item keranjang diperbarui.',
            'cart' => $cartItem->cart->load('items.menu'),
        ]);
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        $this->authorizeCartItem($request, $cartItem);

        $cart = $cartItem->cart;
        $cartItem->delete();
        $this->recalculateCart($cart);

        return response()->json([
            'message' => 'Item keranjang berhasil dihapus.',
            'cart' => $cart->load('items.menu'),
        ]);
    }

    public function checkout(Request $request)
    {
        $cart = Cart::with('items.menu')
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Keranjang kosong.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'metode_pembayaran' => ['required', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date'],
        ]);

        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'cart_id' => $cart->id,
            'name' => $request->user()->name,
            'tanggal' => now(),
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
            'message' => 'Checkout berhasil. Lanjutkan pembayaran untuk menyelesaikan pesanan.',
            'transaction' => $transaction->load('cart.items.menu'),
            'payment' => $payment,
        ], Response::HTTP_CREATED);
    }

    protected function recalculateCart(Cart $cart): void
    {
        $totalItems = $cart->items()->sum('quantity') ?? 0;
        $subtotal = $cart->items()->sum('total_price') ?? 0;

        $cart->update([
            'total_items' => $totalItems,
            'subtotal' => $subtotal,
            'total_price' => $subtotal,
        ]);
    }

    protected function authorizeCartItem(Request $request, CartItem $cartItem): void
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Akses ditolak.');
        }
    }
}
