<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Resit Transaksi Yuran PIBG SK Sri Petaling</title>
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

        .section {
            padding: 18px 22px;
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

        .grid {
            width: 100%;
            border-collapse: collapse;
        }

        .grid td {
            width: 50%;
            vertical-align: top;
            padding: 0 10px 16px 0;
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

        .children-title {
            margin: 0 0 10px;
            font-size: 10px;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #71717a;
            font-weight: 700;
        }

        .child {
            border: 1px solid #e4e4e7;
            border-radius: 10px;
            background: #fafafa;
            padding: 10px 12px;
            margin-bottom: 8px;
        }

        .child-name {
            font-size: 13px;
            font-weight: 700;
            margin: 0;
        }

        .child-meta {
            margin: 2px 0 0;
            color: #52525b;
            font-size: 11px;
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
                            <p class="hero-title">Resit Transaksi Yuran PIBG</p>
                            <p class="hero-sub">Rujukan {{ $transaction->receipt_uuid }}</p>
                        </td>
                        <td style="text-align: right; width: 70px;"><img src="{{ $schoolLogoPdfSource ?? public_path('images/sksp-logo.png') }}" alt="Logo Sekolah" class="hero-logo"></td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <table class="grid">
                    <tr>
                        <td>
                            <p class="label">Kod keluarga</p>
                            <p class="value">{{ $transaction->familyBilling->family_code }}</p>
                        </td>
                        <td>
                            <div class="amount-box">
                                <p class="label">Jumlah</p>
                                <p class="value">RM {{ number_format($transaction->amount, 2) }}</p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p class="label">Order ID</p>
                            <p class="value">{{ $transaction->external_order_display }}</p>
                        </td>
                        <td>
                            <p class="label">Status</p>
                            <p class="value">{{ ucfirst($transaction->status) }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p class="label">Maklumat pembayar</p>
                            <p class="muted">Nama: {{ $transaction->payer_name ?: '-' }}</p>
                            <p class="muted">Emel: {{ $transaction->payer_email ?: '-' }}</p>
                            <p class="muted">Telefon: {{ $transaction->payer_phone ?: '-' }}</p>
                        </td>
                        <td>
                            <p class="label">Invoice dan masa bayar</p>
                            <p class="muted">Invoice: {{ $transaction->provider_invoice_no ?: 'Belum dijana' }}</p>
                            <p class="muted">Bayar pada: {{ $transaction->paid_at?->format('d M Y H:i') ?: '-' }}</p>
                            <p class="muted">Bill code: {{ $transaction->provider_bill_code ?: '-' }}</p>
                        </td>
                    </tr>
                </table>
            </div>

            <hr class="divider">

            <div class="section">
                <p class="children-title">Senarai anak</p>
                @forelse($familyChildren as $child)
                    <div class="child">
                        <p class="child-name">{{ $child->full_name }}</p>
                        <p class="child-meta">{{ $child->class_name ?: '-' }}</p>
                        <p class="child-meta">No. Murid: {{ $child->student_no }}</p>
                    </div>
                @empty
                    <p class="muted">Tiada rekod anak untuk keluarga ini.</p>
                @endforelse

                <p class="footer-note">Resit dijana pada {{ now()->format('d/m/Y H:i') }}</p>
            </div>
        </div>
    </div>
</body>
</html>
