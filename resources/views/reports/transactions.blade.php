<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Laporan Rinci Keuangan Yummy Bebz</title>
    <style>
        body {
            font-family: sans-serif;
            color: #333;
            line-height: 1.4;
            font-size: 11px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0;
            color: #FDC700;
            font-size: 22px;
        }

        .summary-box {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .summary-box td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 13px;
        }

        .day-section {
            margin-top: 20px;
            page-break-inside: avoid;
        }

        .day-title {
            background-color: #f4f4f4;
            padding: 6px;
            font-weight: bold;
            border-left: 4px solid #FDC700;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .table-rinci {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .table-rinci th,
        .table-rinci td {
            border: 1px solid #eee;
            padding: 6px;
            text-align: left;
        }

        .table-rinci th {
            background-color: #f9f9f9;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .no-data {
            color: #999;
            font-style: italic;
            padding: 5px;
        }
    </style>
</head>

<body>

    <div class="header">
        <h2>YUMMY BEBZ</h2>
        <p style="margin: 5px 0; font-weight: bold;">LAPORAN RINCIAN TRANSAKSI HARIAN</p>
        <p style="margin: 0; font-size: 11px; color: #666;">Periode: {{ strtoupper($type) }} ({{ $selected_year }})</p>
    </div>

    <table class="summary-box">
        <tr>
            <td><strong>Total Seluruh Pesanan:</strong> {{ $total_pesanan }} Pesanan</td>
            <td><strong>Total Keuntungan:</strong> Rp {{ number_format($total_profit, 0, ',', '.') }}</td>
        </tr>
    </table>

    <hr style="border: 0; border-top: 1px dashed #ccc;">

    @foreach ($detail as $hari)
        <div class="day-section">
            <div class="day-title">{{ $hari['label'] }}</div>

            @if ($hari['transactions']->isEmpty())
                <div class="no-data">* Tidak ada transaksi pada hari ini.</div>
            @else
                <table class="table-rinci">
                    <thead>
                        <tr>
                            <th width="20%">Nama Customer</th>
                            <th width="30%">Menu Yang Dipesan</th>
                            <th width="8%" class="text-center">Qty</th>
                            <th width="12%" class="text-right">Harga Satuan</th>
                            <th width="15%" class="text-right">Total Harga</th>
                            <th width="15%" class="text-right">Keuntungan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hari['transactions'] as $trx)
                            {{-- Looping relasi details dari model Transaction --}}
                            @foreach ($trx->details as $index => $item)
                                <tr>
                                    {{-- Menggabungkan baris nama customer jika memesan lebih dari 1 menu --}}
                                    @if ($index === 0)
                                        <td rowspan="{{ $trx->details->count() }}"
                                            style="vertical-align: top; font-weight: bold;">
                                            {{ $trx->customer_name ?? ($trx->name ?? 'Guest') }}
                                        </td>
                                    @endif

                                    {{-- Kolom Menu, Qty, Harga Satuan --}}
                                    <td>{{ $item->menu->nama_menu ?? 'Menu N/A' }}</td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-right">Rp {{ number_format($item->harga_satuan ?? 0, 0, ',', '.') }}
                                    </td>

                                    {{-- Kolom Akuntansi gabungan per nomor transaksi --}}
                                    @if ($index === 0)
                                        <td rowspan="{{ $trx->details->count() }}" class="text-right"
                                            style="vertical-align: middle;">
                                            Rp {{ number_format($trx->total_harga, 0, ',', '.') }}
                                        </td>
                                        <td rowspan="{{ $trx->details->count() }}" class="text-right"
                                            style="vertical-align: middle; font-weight: bold; background-color: #fffdf5;">
                                            Rp {{ number_format($trx->total_keuntungan, 0, ',', '.') }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

</body>

</html>
