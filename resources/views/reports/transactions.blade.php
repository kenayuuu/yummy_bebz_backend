<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h2>Laporan Transaksi YUMMY BEBZ</h2>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Pelanggan</th>
                <th>Menu</th>
                <th>Tanggal</th>
                <th>Pembayaran</th>
                <th>Jumlah</th>
                <th>Total Harga</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $transaction)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $transaction->user->name ?? '-' }}</td>
                    <td>{{ $transaction->menu->nama_menu ?? '-' }}</td>
                    <td>{{ $transaction->tanggal }}</td>
                    <td>{{ $transaction->metode_pembayaran }}</td>
                    <td>{{ $transaction->jumlah }}</td>
                    <td>Rp{{ number_format($transaction->total_harga, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Belum ada transaksi.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
