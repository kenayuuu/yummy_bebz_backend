<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        try {
            $reportData = $this->calculateReportData($request);

            return response()->json([
                'total_profit' => (float) $reportData['total_profit'],
                'total_pesanan' => (int) $reportData['total_pesanan'],
                'detail' => $reportData['detail'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            // Mengambil kalkulasi data yang sama persis dengan index API
            $reportData = $this->calculateReportData($request);

            $data = [
                'type'           => $request->type ?? 'weekly',
                'selected_week'  => (int) ($request->week ?? 1),
                'selected_month' => (int) ($request->month ?? now()->month),
                'selected_year'  => (int) ($request->year ?? now()->year),
                'total_pesanan'  => $reportData['total_pesanan'],
                'total_profit'   => $reportData['total_profit'],
                'detail'         => $reportData['detail'],
            ];

            // Load view template PDF
            $pdf = Pdf::loadView('reports.transactions', $data);

            // Nama file menggunakan timestamp agar browser/device terpaksa memuat ulang tampilan baru
            return $pdf->download('Laporan_Keuangan_' . ($request->type ?? 'weekly') . '_' . time() . '.pdf');
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal membuat PDF: ' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Fungsi Privat: Pusat validasi data laporan berdasarkan field tabel transaksi asli
     */
    private function calculateReportData(Request $request)
    {
        Carbon::setLocale('id');

        $type = $request->type ?? 'weekly';
        $year = (int) ($request->year ?? now()->year);
        $month = (int) ($request->month ?? now()->month);

        // Eager loading langsung ke relasi 'details.menu' berdasarkan model kamu
        $query = Transaction::with(['details.menu']);

        // Filter transaksi yang valid menghasilkan uang
        $query->whereIn('status', ['paid', 'completed']);

        $startOfWeekSelected = null;
        $endOfWeekSelected = null;

        if ($type === 'weekly') {
            $week = (int) ($request->week ?? 1);
            $firstDayOfMonth = Carbon::create($year, $month, 1);
            $startOfFirstWeek = $firstDayOfMonth->copy()->startOfWeek(Carbon::MONDAY);

            $startOfWeekSelected = $startOfFirstWeek->copy()->addWeeks($week - 1)->startOfDay();
            $endOfWeekSelected = $startOfWeekSelected->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            $query->whereBetween('tanggal', [$startOfWeekSelected->toDateString(), $endOfWeekSelected->toDateString()]);
        } elseif ($type === 'monthly') {
            $query->whereYear('tanggal', $year)->whereMonth('tanggal', $month);
        } elseif ($type === 'yearly') {
            $query->whereYear('tanggal', $year);
        }

        $transactions = $query->orderBy('tanggal', 'asc')->get();
        $detailGrouped = [];

        $groupedByDate = $transactions->groupBy(function ($trx) {
            return Carbon::parse($trx->tanggal)->toDateString();
        });

        if ($type === 'weekly') {
            $period = $startOfWeekSelected->copy();
            while ($period <= $endOfWeekSelected) {
                $dateString = $period->toDateString();
                $detailGrouped[] = [
                    'label' => $period->translatedFormat('l, d F Y'),
                    'transactions' => $groupedByDate->get($dateString, collect())
                ];
                $period->addDay();
            }
        } elseif ($type === 'monthly') {
            $daysInMonth = Carbon::create($year, $month)->daysInMonth;
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $currentDate = Carbon::create($year, $month, $i);
                $dateString = $currentDate->toDateString();
                $detailGrouped[] = [
                    'label' => $currentDate->translatedFormat('l, d F Y'),
                    'transactions' => $groupedByDate->get($dateString, collect())
                ];
            }
        } else {
            for ($i = 1; $i <= 12; $i++) {
                $monthTransactions = $transactions->filter(function ($trx) use ($i) {
                    return Carbon::parse($trx->tanggal)->month == $i;
                });
                $detailGrouped[] = [
                    'label' => Carbon::create($year, $i, 1)->translatedFormat('F Y'),
                    'transactions' => $monthTransactions
                ];
            }
        }

        return [
            'total_pesanan' => $transactions->count(),
            'total_profit'  => (float) $transactions->sum('total_keuntungan'),
            'detail'        => $detailGrouped
        ];
    }
}
