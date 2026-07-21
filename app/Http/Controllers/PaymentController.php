<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;
use App\Helpers\NotificationHelper;

class PaymentController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$clientKey = config('services.midtrans.clientKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function index(Request $request)
    {
        $query = Payment::with(['transaction', 'paymentMethod']);

        if ($request->user()->role === 'customer') {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->latest()->get());
    }

    public function show(Request $request, Payment $payment)
    {
        if (
            $request->user()->role === 'customer' &&
            $payment->user_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Pembayaran tidak ditemukan.'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            $payment->load(['transaction', 'paymentMethod'])
        );
    }

    public function snap(Transaction $transaction)
    {
        if (
            auth()->user()->role === 'customer' &&
            $transaction->user_id !== auth()->id()
        ) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan.'
            ], 404);
        }

        $payment = $transaction->payment;

        if (!$payment) {
            return response()->json([
                'message' => 'Data pembayaran tidak ditemukan.'
            ], 404);
        }

        if ($payment->status === 'paid') {
            return response()->json([
                'message' => 'Transaksi sudah dibayar.'
            ], 400);
        }

        if ($payment->snap_token) {
            return response()->json([
                'snap_token' => $payment->snap_token,
                'order_id' => $payment->order_id,
            ]);
        }

        $orderId = 'ORDER-' . $transaction->id . '-' . time();

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $payment->amount,
            ],
            'callbacks' => [
                'finish' => url('/payment/success'),
            ],
            'customer_details' => [
                'first_name' => $transaction->user->name,
                'email' => $transaction->user->email,
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);

            Log::info($snapToken);

            $midtransMethod = PaymentMethod::where('code', 'midtrans')->first();

            $payment->update([
                'order_id' => $orderId,
                'snap_token' => $snapToken,
                'payment_method_id' => $midtransMethod ? $midtransMethod->id : $payment->payment_method_id,
            ]);

            return response()->json([
                'message' => 'Snap token berhasil dibuat.',
                'snap_token' => $snapToken,
                'redirect_url' => "https://app.sandbox.midtrans.com/snap/v4/redirection/" . $snapToken,
                'order_id' => $orderId,
            ]);
        } catch (\Exception $e) {
            Log::error('MIDTRANS SNAP ERROR', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal membuat Snap Token.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Callback Midtrans
     */
    public function notification(Request $request)
    {
        Log::info("===== MIDTRANS CALLBACK =====");
        Log::info($request->all());

        $notification = new Notification();

        $signature = hash(
            'sha512',
            $notification->order_id .
                $notification->status_code .
                $notification->gross_amount .
                config('services.midtrans.serverKey')
        );

        if ($signature !== $notification->signature_key) {
            return response()->json([
                'message' => 'Invalid Signature'
            ], 403);
        }

        DB::beginTransaction();

        try {
            $payment = Payment::where(
                'order_id',
                $notification->order_id
            )->first();

            if (!$payment) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Payment tidak ditemukan.'
                ], 404);
            }

            $payment->transaction_id_midtrans = $notification->transaction_id;
            $payment->reference = $notification->transaction_id;

            $midtransMethod = PaymentMethod::where('code', 'midtrans')->first();
            if ($midtransMethod) {
                $payment->payment_method_id = $midtransMethod->id;
            }

            switch ($notification->transaction_status) {
                case 'capture':
                    if ($notification->fraud_status == 'challenge') {
                        $payment->status = 'pending';
                    } else {
                        $payment->status = 'paid';
                        $payment->paid_at = now();

                        if ($payment->transaction) {
                            $payment->transaction->update([
                                'status' => 'paid'
                            ]);
                        }
                    }
                    break;

                case 'settlement':
                    $payment->status = 'paid';
                    $payment->paid_at = now();

                    if ($payment->transaction) {
                        $payment->transaction->update([
                            'status' => 'paid'
                        ]);
                    }
                    break;

                case 'pending':
                    $payment->status = 'pending';
                    if ($payment->transaction) {
                        $payment->transaction->update([
                            'status' => 'pending'
                        ]);
                    }
                    break;

                case 'expire':
                case 'deny':
                case 'cancel':
                    $payment->status = ($notification->transaction_status === 'cancel') ? 'cancelled' : 'failed';

                    if ($payment->transaction) {
                        $payment->transaction->update([
                            'status' => 'cancelled'
                        ]);
                    }
                    break;
            }

            $payment->save();

            DB::commit();

            return response()->json([
                'message' => 'OK'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('MIDTRANS CALLBACK ERROR', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function complete(Transaction $transaction)
    {
        if ($transaction->status !== 'ready') {
            return response()->json([
                'message' => 'Pesanan belum siap diselesaikan.'
            ], 422);
        }

        $transaction->update([
            'status' => 'paid',
        ]);

        NotificationHelper::send(
            $transaction->user,
            'Pesanan Selesai',
            'Terima kasih telah berbelanja di Yummy Bebz.',
            'transaction',
            [
                'reference_id' => $transaction->id,
                'transaction_id' => $transaction->id,
                'status' => 'paid',
            ]
        );

        return response()->json([
            'message' => 'Pesanan selesai.',
            'data' => $transaction->fresh()->load([
                'details.menu',
                'payment',
                'paymentMethod',
                'user',
            ]),
        ]);
    }
}
