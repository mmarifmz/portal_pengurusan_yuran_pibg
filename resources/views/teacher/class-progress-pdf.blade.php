<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Laporan Kutipan PIBG - {{ $report['class_name'] }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 15mm 12mm 18mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #27272a;
            font-size: 9px;
            line-height: 1.35;
        }

        .footer {
            position: fixed;
            right: 0;
            bottom: -11mm;
            left: 0;
            border-top: 1px solid #d4d4d8;
            padding-top: 2.5mm;
            color: #71717a;
            font-size: 8px;
            text-align: right;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #166534;
            margin-bottom: 5mm;
            padding-bottom: 4mm;
        }

        .header td {
            vertical-align: middle;
        }

        .logo {
            width: 17mm;
            height: 17mm;
            border-radius: 50%;
        }

        .eyebrow {
            margin: 0;
            color: #15803d;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        h1 {
            margin: 1mm 0 0;
            color: #14532d;
            font-size: 21px;
            line-height: 1.15;
        }

        .header-meta {
            color: #52525b;
            font-size: 9px;
            line-height: 1.55;
            text-align: right;
        }

        .summary-grid {
            width: 100%;
            margin-bottom: 5mm;
            border-collapse: separate;
            border-spacing: 2mm 0;
        }

        .summary-grid td {
            width: 12.5%;
            border: 1px solid #d4d4d8;
            border-radius: 7px;
            background: #fafafa;
            padding: 3mm;
            vertical-align: top;
        }

        .summary-grid .paid {
            border-color: #86efac;
            background: #f0fdf4;
        }

        .summary-grid .partial {
            border-color: #fde68a;
            background: #fffbeb;
        }

        .summary-grid .unpaid {
            border-color: #fecaca;
            background: #fef2f2;
        }

        .summary-grid .money {
            border-color: #bae6fd;
            background: #f0f9ff;
        }

        .summary-grid .money .metric-value {
            font-size: 12px;
            white-space: nowrap;
        }

        .metric-label {
            margin: 0;
            color: #71717a;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .metric-value {
            margin: 1.2mm 0 0;
            color: #18181b;
            font-size: 15px;
            font-weight: bold;
        }

        .section {
            margin-top: 4mm;
            page-break-inside: auto;
        }

        .section-header {
            width: 100%;
            margin-bottom: 2mm;
            border-collapse: collapse;
        }

        .section-title {
            margin: 0;
            color: #18181b;
            font-size: 13px;
        }

        .section-count {
            color: #71717a;
            font-size: 8px;
            font-weight: bold;
            text-align: right;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        .report-table thead {
            display: table-header-group;
        }

        .report-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .report-table th {
            border: 1px solid #d4d4d8;
            background: #f4f4f5;
            padding: 1.6mm;
            color: #3f3f46;
            font-size: 7px;
            letter-spacing: .05em;
            text-align: left;
            text-transform: uppercase;
        }

        .report-table td {
            border: 1px solid #e4e4e7;
            padding: 1.6mm;
            vertical-align: top;
        }

        .report-table tbody tr:nth-child(even) td {
            background: #fafafa;
        }

        .number,
        .amount {
            white-space: nowrap;
        }

        .number {
            width: 8mm;
            text-align: center;
        }

        .amount {
            text-align: right;
        }

        .primary-name {
            font-weight: bold;
            color: #18181b;
        }

        .secondary {
            margin-top: .6mm;
            color: #71717a;
            font-size: 7.5px;
        }

        .status {
            font-weight: bold;
        }

        .status-paid {
            color: #15803d;
        }

        .status-partial {
            color: #b45309;
        }

        .status-unpaid {
            color: #be123c;
        }

        .empty {
            border: 1px solid #d4d4d8;
            border-radius: 7px;
            background: #fafafa;
            padding: 4mm;
            color: #71717a;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $summary = $report['summary'];
        $paidEntries = collect($report['paid_entries']);
        $unpaidEntries = collect($report['unpaid_entries']);
        $generatedTimestamp = $generatedAt
            ->copy()
            ->timezone(config('app.timezone'))
            ->format('d/m/Y H:i:s');
    @endphp

    <div class="footer">
        Laporan dijana pada {{ $generatedTimestamp }} ({{ config('app.timezone') }})
    </div>

    <table class="header">
        <tr>
            <td style="width: 22mm;">
                <img src="{{ $schoolLogoSource }}" alt="Logo sekolah" class="logo">
            </td>
            <td>
                <p class="eyebrow">Portal Sumbangan PIBG</p>
                <h1>Laporan Kutipan Mengikut Kelas</h1>
            </td>
            <td class="header-meta">
                <strong>Kelas:</strong> {{ $summary['class_name'] }}<br>
                <strong>Guru kelas:</strong> {{ $summary['teacher_name'] }}<br>
                <strong>Sesi:</strong> {{ $report['billing_year'] }}
            </td>
        </tr>
    </table>

    <table class="summary-grid">
        <tr>
            <td>
                <p class="metric-label">Jumlah Keluarga</p>
                <p class="metric-value">{{ $summary['total_families'] }}</p>
            </td>
            <td class="paid">
                <p class="metric-label">Dah Bayar</p>
                <p class="metric-value">{{ $summary['fully_paid_families'] }}</p>
            </td>
            <td class="partial">
                <p class="metric-label">Sebahagian</p>
                <p class="metric-value">{{ $summary['partial_paid_families'] }}</p>
            </td>
            <td class="unpaid">
                <p class="metric-label">Belum Bayar</p>
                <p class="metric-value">{{ $summary['unpaid_families'] }}</p>
            </td>
            <td>
                <p class="metric-label">Kadar Selesai</p>
                <p class="metric-value">{{ number_format((float) $summary['completion_percent'], 2) }}%</p>
            </td>
            <td class="money">
                <p class="metric-label">Sumbangan PIBG</p>
                <p class="metric-value">RM {{ number_format((float) $summary['yuran_collected'], 2) }}</p>
            </td>
            <td class="money">
                <p class="metric-label">Jumlah Kutipan</p>
                <p class="metric-value">RM {{ number_format((float) $summary['jumlah_kutipan'], 2) }}</p>
            </td>
            <td class="unpaid">
                <p class="metric-label">Baki Tertunggak</p>
                <p class="metric-value">RM {{ number_format((float) $summary['baki_tertunggak'], 2) }}</p>
            </td>
        </tr>
    </table>

    <section class="section">
        <table class="section-header">
            <tr>
                <td><h2 class="section-title">Dah Bayar</h2></td>
                <td class="section-count">{{ $paidEntries->count() }} keluarga</td>
            </tr>
        </table>

        @if ($paidEntries->isEmpty())
            <div class="empty">Tiada rekod bayaran untuk kelas ini.</div>
        @else
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="number">Bil.</th>
                        <th>Murid</th>
                        <th>Kod Keluarga</th>
                        <th>Status</th>
                        <th>Tarikh Bayaran</th>
                        <th class="amount">Sumbangan PIBG</th>
                        <th class="amount">Sumbangan Tambahan</th>
                        <th class="amount">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paidEntries as $entry)
                        @php
                            $entryTotal = (float) $entry['paid_amount'] + (float) $entry['donation_total'];
                        @endphp
                        <tr>
                            <td class="number">{{ $loop->iteration }}</td>
                            <td>
                                <div class="primary-name">{{ $entry['primary_student_name'] ?: '-' }}</div>
                                @if (! empty($entry['sibling_names']))
                                    <div class="secondary">Adik-beradik: {{ implode(', ', $entry['sibling_names']) }}</div>
                                @endif
                            </td>
                            <td>{{ $entry['family_code'] }}</td>
                            <td class="status {{ $entry['is_partial'] ? 'status-partial' : 'status-paid' }}">
                                {{ $entry['status_label'] }}
                            </td>
                            <td>{{ $entry['latest_payment_at'] ?: '-' }}</td>
                            <td class="amount">RM {{ number_format((float) $entry['paid_amount'], 2) }}</td>
                            <td class="amount">RM {{ number_format((float) $entry['donation_total'], 2) }}</td>
                            <td class="amount"><strong>RM {{ number_format($entryTotal, 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="section">
        <table class="section-header">
            <tr>
                <td><h2 class="section-title">Belum Bayar</h2></td>
                <td class="section-count">{{ $unpaidEntries->count() }} keluarga</td>
            </tr>
        </table>

        @if ($unpaidEntries->isEmpty())
            <div class="empty">Tiada keluarga tertunggak untuk kelas ini.</div>
        @else
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="number">Bil.</th>
                        <th>Murid</th>
                        <th>Kod Keluarga</th>
                        <th>Penjaga</th>
                        <th>Telefon</th>
                        <th>Status</th>
                        <th>Rekod Terdahulu</th>
                        <th class="amount">Baki</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($unpaidEntries as $entry)
                        <tr>
                            <td class="number">{{ $loop->iteration }}</td>
                            <td>
                                <div class="primary-name">{{ $entry['primary_student_name'] ?: '-' }}</div>
                                @if (! empty($entry['sibling_names']))
                                    <div class="secondary">Adik-beradik: {{ implode(', ', $entry['sibling_names']) }}</div>
                                @endif
                            </td>
                            <td>{{ $entry['family_code'] }}</td>
                            <td>{{ $entry['parent_name'] ?: '-' }}</td>
                            <td>{{ $entry['parent_phone'] ?: '-' }}</td>
                            <td class="status status-unpaid">{{ $entry['status_label'] }}</td>
                            <td>{{ $entry['previous_paid_year'] ? 'Bayar '.$entry['previous_paid_year'] : '-' }}</td>
                            <td class="amount"><strong>RM {{ number_format((float) $entry['balance_amount'], 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</body>
</html>
