<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Resit Sejarah Bayaran {{ $selectedYear }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #18181b;
            margin: 0;
            font-size: 12px;
            line-height: 1.45;
        }

        .page {
            padding: 24px;
        }

        .card {
            border: 1px solid #d4d4d8;
            border-radius: 14px;
            overflow: hidden;
        }

        .hero {
            background: #14532d;
            color: #ffffff;
            padding: 18px 22px;
        }

        .hero-header {
            width: 100%;
            border-collapse: collapse;
        }

        .hero-header td {
            vertical-align: top;
            color: #ffffff;
        }

        .hero-logo {
            width: 56px;
            height: 56px;
            border-radius: 999px;
        }

        .hero-meta {
            margin: 0;
            font-size: 10px;
            letter-spacing: .12em;
            text-transform: uppercase;
            opacity: 0.9;
        }

        .hero-title {
            margin: 6px 0 0;
            font-size: 22px;
            font-weight: 700;
        }

        .hero-sub {
            margin: 6px 0 0;
            font-size: 11px;
            opacity: 0.9;
        }

        .hero p {
            color: #ffffff;
        }

        .section {
            padding: 18px 22px;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
        }

        .grid td {
            width: 50%;
            vertical-align: top;
            padding: 0 10px 12px 0;
        }

        .label {
            margin: 0;
            font-size: 10px;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #71717a;
            font-weight: 700;
        }

        .value {
            margin: 6px 0 0;
            font-size: 15px;
            font-weight: 700;
            color: #111827;
        }

        .muted {
            margin: 4px 0 0;
            color: #52525b;
        }

        .amount-box {
            border: 1px solid #86efac;
            background: #ecfdf5;
            border-radius: 10px;
            padding: 12px;
        }

        .amount-box .value {
            font-size: 28px;
            color: #047857;
            margin-top: 2px;
        }

        .divider {
            border-top: 1px solid #e4e4e7;
            margin: 0;
        }

        .section-title {
            margin: 0 0 10px;
            font-size: 10px;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #71717a;
            font-weight: 700;
        }

        .table-wrap {
            border: 1px solid #e4e4e7;
            border-radius: 10px;
            overflow: hidden;
            background: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #f4f4f5;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-size: 10px;
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #e4e4e7;
        }

        tbody td {
            padding: 8px;
            border-top: 1px solid #f1f5f9;
            color: #111827;
        }

        .text-right {
            text-align: right;
        }

        .footer-note {
            margin-top: 10px;
            font-size: 10px;
            color: #71717a;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div class="hero">
                <table class="hero-header">
                    <tr>
                        <td>
                            <p class="hero-meta">Portal Yuran PIBG</p>
                            <p class="hero-title">Resit Sejarah Bayaran Tahun Lepas</p>
                            <p class="hero-sub">Tahun {{ $selectedYear }} | Dijana {{ $generatedAt->format('d M Y H:i') }}</p>
                        </td>
                        <td style="text-align: right; width: 70px;"><img src="{{ $schoolLogoPdfSource ?? $schoolLogoUrl }}" alt="Logo Sekolah" class="hero-logo"></td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <table class="grid">
                    <tr>
                        <td>
                            <p class="label">Sumber Data</p>
                            <p class="value">Sejarah Bayaran Imported</p>
                            <p class="muted">Rekod bayaran tahun lepas berdasarkan data import portal.</p>
                        </td>
                        <td>
                            <div class="amount-box">
                                <p class="label">Jumlah Bayar</p>
                                <p class="value">RM {{ number_format((float) $totals['paid'], 2) }}</p>
                                <p class="muted">Jumlah Sumbangan: RM {{ number_format((float) $totals['donation'], 2) }}</p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <hr class="divider">

            <div class="section">
                <p class="section-title">Rekod Anak Berkaitan</p>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Student No</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>Family Code</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($children as $child)
                                <tr>
                                    <td>{{ $child->student_no }}</td>
                                    <td>{{ $child->full_name }}</td>
                                    <td>{{ $child->class_name ?: '-' }}</td>
                                    <td>{{ $child->family_code ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section" style="padding-top: 0;">
                <p class="section-title">Butiran Bayaran Imported</p>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Tarikh Bayar</th>
                                <th>Rujukan</th>
                                <th>Anak</th>
                                <th>Kelas</th>
                                <th class="text-right">Jumlah (RM)</th>
                                <th class="text-right">Sumbangan (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($legacyPayments as $payment)
                                <tr>
                                    <td>{{ $payment->paid_at?->format('d M Y H:i') ?: '-' }}</td>
                                    <td>{{ $payment->payment_reference ?: '-' }}</td>
                                    <td>{{ $payment->student_name }}</td>
                                    <td>{{ $payment->display_class_name ?: ($payment->class_name ?: '-') }}</td>
                                    <td class="text-right">{{ number_format((float) $payment->amount_paid, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $payment->donation_amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="footer-note">Dokumen ini dijana daripada data imported yang tersedia di portal.</p>
            </div>
        </div>
    </div>
</body>
</html>
