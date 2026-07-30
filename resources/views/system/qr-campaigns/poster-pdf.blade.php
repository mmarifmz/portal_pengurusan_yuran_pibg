<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>{{ $campaign->poster_title }}</title>
    <style>
        @page { margin: 0; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #173b2c;
            background: #f7fbf7;
        }
        .page {
            width: 100%;
            position: relative;
            overflow: hidden;
            padding: 15mm 17mm 7mm;
            text-align: center;
            background: #f7fbf7;
        }
        .top-band {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 10mm;
            background: #174a34;
        }
        .gold-line {
            position: absolute;
            top: 10mm;
            left: 0;
            right: 0;
            height: 2mm;
            background: #e5b338;
        }
        .brand {
            margin-top: 2mm;
            width: 100%;
            border-collapse: collapse;
        }
        .brand td { vertical-align: middle; }
        .logo {
            width: 22mm;
            height: 22mm;
            border-radius: 50%;
        }
        .site-name {
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: .04em;
            color: #4b6357;
        }
        .eyebrow {
            margin: 9mm 0 0;
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .18em;
            color: #2f7a55;
        }
        h1 {
            margin: 4mm auto 0;
            max-width: 170mm;
            font-size: 27pt;
            line-height: 1.12;
            color: #123c2a;
        }
        .subtitle {
            margin: 4mm auto 0;
            max-width: 155mm;
            color: #53645a;
            font-size: 12pt;
            line-height: 1.5;
        }
        .source-strip {
            margin: 6mm auto 0;
            padding: 3.5mm 5mm;
            border: 1px solid #b9d7c4;
            border-radius: 5mm;
            background: #eaf5ed;
            color: #245d41;
            font-size: 10pt;
            font-weight: bold;
        }
        .qr-shell {
            width: 106mm;
            margin: 7mm auto 0;
            padding: 5mm;
            border: 1.2mm solid #174a34;
            border-radius: 7mm;
            background: #ffffff;
            box-shadow: 0 2mm 6mm rgba(23, 74, 52, .12);
        }
        .qr {
            width: 94mm;
            height: 94mm;
            display: block;
        }
        .cta {
            margin: 6mm 0 0;
            font-size: 18pt;
            font-weight: bold;
            color: #174a34;
        }
        .instruction {
            margin: 2mm 0 0;
            font-size: 10pt;
            color: #65756c;
        }
        .short-url {
            margin: 4mm auto 0;
            display: inline-block;
            padding: 2.7mm 5mm;
            border-radius: 3mm;
            background: #174a34;
            color: white;
            font-size: 10pt;
            font-weight: bold;
        }
        .footer {
            margin-top: 8mm;
            border-top: 1px solid #cfdcd3;
            padding-top: 3mm;
            color: #6d7c73;
            font-size: 8.5pt;
        }
        .footer strong { color: #365646; }
        .corner {
            position: absolute;
            right: -24mm;
            bottom: -24mm;
            width: 65mm;
            height: 65mm;
            border-radius: 50%;
            background: #e5b338;
            opacity: .18;
        }
    </style>
</head>
<body>
    @php
        $purposeLabels = [
            'payment' => 'Bayaran PIBG',
            'donation' => 'Sumbangan',
            'event' => 'Acara Sekolah',
            'programme' => 'Program',
        ];
    @endphp
    <div class="page">
        <div class="top-band"></div>
        <div class="gold-line"></div>
        <div class="corner"></div>

        <table class="brand">
            <tr>
                <td style="width: 28mm; text-align: left;">
                    <img src="{{ $schoolLogoSource }}" alt="Logo sekolah" class="logo">
                </td>
                <td class="site-name">{{ $siteName }}</td>
                <td style="width: 28mm;"></td>
            </tr>
        </table>

        <p class="eyebrow">{{ strtoupper($purposeLabels[$campaign->purpose] ?? $campaign->purpose) }}</p>
        <h1>{{ $campaign->poster_title }}</h1>
        @if ($campaign->poster_subtitle)
            <p class="subtitle">{{ $campaign->poster_subtitle }}</p>
        @endif

        @if ($campaign->class_name || $campaign->location_name)
            <div class="source-strip">
                @if ($campaign->class_name)
                    Kelas: {{ $campaign->class_name }}
                @endif
                @if ($campaign->class_name && $campaign->location_name)
                    &nbsp; | &nbsp;
                @endif
                @if ($campaign->location_name)
                    Lokasi: {{ $campaign->location_name }}
                @endif
            </div>
        @endif

        <div class="qr-shell">
            <img src="{{ $qrDataUri }}" alt="Kod QR {{ $campaign->name }}" class="qr">
        </div>

        <p class="cta">{{ $campaign->call_to_action }}</p>
        <p class="instruction">Buka kamera telefon, imbas kod QR dan ikut arahan di portal rasmi.</p>
        <div class="short-url">{{ $campaign->shortUrl() }}</div>

        <div class="footer">
            <strong>{{ $campaign->name }}</strong>
            @if ($campaign->distribution_channel)
                &nbsp; | &nbsp; Saluran: {{ $campaign->distribution_channel }}
            @endif
            <br>
            Pautan ini menjejak imbasan secara berasingan daripada transaksi bayaran yang disahkan.
        </div>
    </div>
</body>
</html>
