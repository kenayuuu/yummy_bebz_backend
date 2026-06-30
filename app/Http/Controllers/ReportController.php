<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        try {
            $type = $request->type;

            $year = (int) ($request->year ?? now()->year);
            $month = (int) ($request->month ?? now()->month);

            $query = Transaction::query();

            if ($type === 'weekly') {

                $week = (int) ($request->week ?? 1);

                $startDay = 1;
                $endDay = 7;

                if ($week == 2) {
                    $startDay = 8;
                    $endDay = 14;
                } elseif ($week == 3) {
                    $startDay = 15;
                    $endDay = 21;
                } elseif ($week == 4) {
                    $startDay = 22;
                    $endDay = 28;
                } elseif ($week == 5) {
                    $startDay = 29;
                    $endDay = Carbon::create($year, $month)->daysInMonth;
                }

                $start = Carbon::create($year, $month, $startDay)->startOfDay();
                $end = Carbon::create($year, $month, $endDay)->endOfDay();

                $query->whereDate('tanggal', '>=', $start->toDateString())
                    ->whereDate('tanggal', '<=', $end->toDateString());
            } elseif ($type === 'monthly') {
                $query->whereYear('tanggal', $year)
                    ->whereMonth('tanggal', $month);
            } elseif ($type === 'yearly') {
                $query->whereYear('tanggal', $year);
            }

            $transactions = $query->get();

            $totalPesanan = $transactions->count();
            $totalProfit = $transactions->sum('total_keuntungan');

            $detail = [];

            if ($type === 'weekly') {

                $week = (int) ($request->week ?? 1);

                $startDay = match ($week) {
                    2 => 8,
                    3 => 15,
                    4 => 22,
                    5 => 29,
                    default => 1
                };

                $endDay = match ($week) {
                    2 => 14,
                    3 => 21,
                    4 => 28,
                    5 => Carbon::create($year, $month)->daysInMonth,
                    default => 7
                };

                $start = Carbon::create($year, $month, $startDay);
                $end = Carbon::create($year, $month, $endDay);

                $period = Carbon::parse($start);

                while ($period <= $end) {

                    $list = $transactions->filter(function ($trx) use ($period) {
                        return Carbon::parse($trx->tanggal)->isSameDay($period);
                    });

                    $detail[] = [
                        'label' => $period->translatedFormat('l'),
                        'pesanan' => $list->count(),
                        'pendapatan' => $list->sum('total_keuntungan'),
                    ];

                    $period->addDay();
                }
            } elseif ($type === 'monthly') {

                $days = Carbon::create($year, $month)->daysInMonth;

                for ($i = 1; $i <= $days; $i++) {

                    $list = $transactions->filter(function ($trx) use ($i) {
                        return Carbon::parse($trx->tanggal)->day == $i;
                    });

                    $detail[] = [
                        'label' => "Hari $i",
                        'pesanan' => $list->count(),
                        'pendapatan' => $list->sum('total_keuntungan'),
                    ];
                }
            }

            // YEAR DETAIL
            else {

                for ($i = 1; $i <= 12; $i++) {

                    $list = $transactions->filter(function ($trx) use ($i) {
                        return Carbon::parse($trx->tanggal)->month == $i;
                    });

                    $detail[] = [
                        'label' => Carbon::create()->month($i)->translatedFormat('F'),
                        'pesanan' => $list->count(),
                        'pendapatan' => $list->sum('total_keuntungan'),
                    ];
                }
            }

            return response()->json([
                'total_profit' => (float) $totalProfit,
                'total_pesanan' => (int) $totalPesanan,
                'detail' => $detail,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
