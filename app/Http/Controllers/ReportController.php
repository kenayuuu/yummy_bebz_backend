<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function transactions(Request $request)
    {
        $transactions = Transaction::with(['user', 'menu'])
            ->when(
                $request->filled('tanggal_mulai'),
                fn ($query) => $query->whereDate('tanggal', '>=', $request->query('tanggal_mulai'))
            )
            ->when(
                $request->filled('tanggal_selesai'),
                fn ($query) => $query->whereDate('tanggal', '<=', $request->query('tanggal_selesai'))
            )
            ->latest()
            ->get();

        $pdf = Pdf::loadView('reports.transactions', [
            'transactions' => $transactions,
        ]);

        return $pdf->download('laporan-transaksi.pdf');
    }
}
