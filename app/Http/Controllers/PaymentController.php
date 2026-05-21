<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['transaction']);

        if ($request->user()->role === 'customer') {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->latest()->get());
    }

    public function show(Request $request, Payment $payment)
    {
        if ($request->user()->role === 'customer' && $payment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Pembayaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($payment->load('transaction'));
    }
}
