<!DOCTYPE html>
<html lang="ms">
<head>
    @php
        $title = 'Resit Bayaran | Portal Yuran PIBG SK Sri Petaling';
    @endphp
    @include('partials.head')
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f4f5; color: #18181b; }
        .topbar { background: #ffffff; border-bottom: 1px solid #e4e4e7; }
        .topbar-inner { width: min(100%, 980px); margin: 0 auto; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .brand { display: flex; align-items: center; gap: 10px; color: #14532d; text-decoration: none; }
        .brand img { width: 40px; height: 40px; border-radius: 999px; border: 1px solid #d4d4d8; background: #fff; padding: 3px; }
        .brand-title { margin: 0; font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: #52525b; }
        .brand-sub { margin: 2px 0 0; font-size: 17px; font-weight: 700; color: #14532d; }
        .back-link { border: 1px solid #d4d4d8; border-radius: 10px; padding: 8px 12px; font-size: 13px; font-weight: 600; text-decoration: none; color: #1f2937; background: #fff; }
        .back-link:hover { background: #f9fafb; }
        main { min-height: calc(100vh - 68px); display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { width: min(100%, 900px); background: #fff; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 60px rgba(24, 24, 27, 0.12); }
        .hero { background: #14532d; color: #fff; padding: 28px 32px; }
        .hero p { margin: 0; }
        .meta { color: rgba(255, 255, 255, 0.7); font-size: 12px; text-transform: uppercase; letter-spacing: 0.18em; }
        .title { margin-top: 8px; font-size: 30px; font-weight: 700; }
        .sub { margin-top: 8px; font-size: 14px; color: rgba(255, 255, 255, 0.8); }
        .grid { display: grid; gap: 24px; padding: 32px; }
        .cols { display: grid; gap: 20px; }
        .label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.14em; color: #71717a; font-weight: 700; }
        .value { margin-top: 8px; font-size: 18px; font-weight: 600; color: #18181b; }
        .muted { margin-top: 4px; font-size: 14px; color: #52525b; }
        .amount { border-radius: 18px; background: #ecfdf5; padding: 20px; }
        .amount .value { font-size: 38px; color: #047857; }
        .actions { margin-top: 16px; display: flex; flex-wrap: wrap; gap: 8px; }
        .wa-btn { display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; padding: 10px 14px; font-size: 13px; font-weight: 700; text-decoration: none; border: 1px solid #22c55e; color: #166534; background: #ecfdf5; }
        .wa-btn:hover { background: #dcfce7; }
        .children { display: grid; gap: 12px; }
        .child { border: 1px solid #e4e4e7; border-radius: 18px; background: #fafafa; padding: 14px 16px; }
        .child-name { font-size: 15px; font-weight: 700; color: #18181b; }
        .child-meta { margin-top: 4px; font-size: 12px; color: #71717a; }
        @media (min-width: 768px) { .cols { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a href="{{ $portalUrl ?? route('home') }}" class="brand">
                <img src="{{ $schoolLogoUrl ?? asset('images/sksp-logo.png') }}" alt="Logo SK Sri Petaling">
                <div>
                    <p class="brand-title">Portal Rasmi</p>
                    <p class="brand-sub">Yuran &amp; Sumbangan PIBG SK Sri Petaling</p>
                </div>
            </a>
            <a href="{{ $backUrl ?? ($portalUrl ?? route('home')) }}" class="back-link">{{ $backLabel ?? 'Back to portal' }}</a>
        </div>
    </header>

    <main>
        <section class="card">
            <div class="hero">
                <p class="meta">Portal Yuran PIBG</p>
                <h1 class="title">Resit Bayaran</h1>
                <p class="sub">Rujukan {{ $transaction->receipt_uuid }}</p>
            </div>

            <div class="grid">
                @unless ($isPublicReceipt ?? true)
                    <div class="actions">
                        <a href="{{ $teacherShareUrl }}" target="_blank" rel="noopener" class="wa-btn">Share with Teacher (WhatsApp)</a>
                    </div>
                @endunless
                <div class="cols">
                    <div>
                        <p class="label">Kod keluarga</p>
                        <p class="value">{{ $transaction->familyBilling->family_code }}</p>
                    </div>
                    <div class="amount">
                        <p class="label">Jumlah</p>
                        <p class="value">RM {{ number_format($transaction->amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="label">Order ID</p>
                        <p class="value">{{ $displayOrderId ?? $transaction->external_order_id }}</p>
                    </div>
                    <div>
                        <p class="label">Status</p>
                        <p class="value">{{ ucfirst($transaction->status) }}</p>
                    </div>
                    <div>
                        <p class="label">Maklumat pembayar</p>
                        <p class="muted">{{ $displayPayerEmail ?? ($transaction->payer_email ?: '-') }}</p>
                        <p class="muted">{{ $displayPayerPhone ?? ($transaction->payer_phone ?: '-') }}</p>
                    </div>
                    <div>
                        <p class="label">Invoice dan masa bayar</p>
                        <p class="muted">Invoice: {{ $displayInvoiceNo ?? ($transaction->provider_invoice_no ?: 'Belum dijana') }}</p>
                        <p class="muted">Bayar pada: {{ $transaction->paid_at?->format('d M Y H:i') ?: '–' }}</p>
                    </div>
                </div>

                <div>
                    <p class="label">Senarai anak</p>
                    <div class="children" style="margin-top: 12px;">
                        @foreach($familyChildren as $child)
                            <div class="child">
                                <div class="child-name">{{ $child->display_name ?? $child->full_name }}</div>
                                <div class="child-meta">{{ $child->class_name }}</div>
                                <div class="child-meta">No. Murid: {{ $child->display_student_no ?? $child->student_no }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
