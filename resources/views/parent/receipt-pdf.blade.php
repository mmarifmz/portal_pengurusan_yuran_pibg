<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Resit Yuran PIBG</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1f2a24; }
        .card { width: 100%; max-width: 720px; margin: 0 auto; padding: 24px; border: 1px solid #e5e7eb; }
        h1, h2 { margin: 0; }
        .section { margin-top: 20px; }
        .section-title { font-size: 14px; text-transform: uppercase; letter-spacing: .1em; color: #6b7280; }
        .row { display: flex; justify-content: space-between; margin-top: 8px; }
    </style>
</head>
<body>
<div class="card">
    <div style="text-align:center;">
        <img src="{{ public_path('images/sksp-logo.png') }}" alt="Logo" width="60">
        <h1>Portal Yuran & Sumbangan PIBG</h1>
        <p>SK Sri Petaling</p>
    </div>

    <div class="section">
        <p class="section-title">Maklumat Transaksi</p>
        <div class="row">
            <span>Order ID</span>
            <strong>{{ $transaction->external_order_id }}</strong>
        </div>
        <div class="row">
            <span>Bill Code</span>
            <strong>{{ $transaction->provider_bill_code }}</strong>
        </div>
        <div class="row">
            <span>Jumlah Bayaran</span>
            <strong>RM {{ number_format($transaction->amount, 2) }}</strong>
        </div>
        <div class="row">
            <span>Status</span>
            <strong>{{ ucfirst($transaction->status) }}</strong>
        </div>
        <div class="row">
            <span>Tarikh Dibayar</span>
            <strong>{{ $transaction->paid_at?->format('d/m/Y H:i') ?? '-' }}</strong>
        </div>
    </div>

    <div class="section">
        <p class="section-title">Maklumat Pembayar</p>
        <div class="row">
            <span>Email</span>
            <strong>{{ $transaction->payer_email ?? '-' }}</strong>
        </div>
        <div class="row">
            <span>Telefon</span>
            <strong>{{ $transaction->payer_phone ?? '-' }}</strong>
        </div>
    </div>

    <div class="section">
        <p class="section-title">Anak Dalam Keluarga</p>
        @foreach($familyChildren as $child)
            <div class="row">
                <span>{{ $child->full_name }} ({{ $child->student_no }})</span>
                <span>{{ $child->class_name }}</span>
            </div>
        @endforeach
    </div>
</div>
</body>
</html>
