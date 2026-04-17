<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php
    $seoDefaults = [
        'seo_site_title' => 'Portal Yuran PIBG SK Sri Petaling',
        'seo_description' => 'Portal rasmi semakan dan pembayaran Yuran & Sumbangan PIBG SK Sri Petaling, didukung oleh Avante Intelligence dan Arif.my sebagai inisiatif pendigitalan pendidikan sekolah.',
        'seo_keywords' => 'Portal Yuran PIBG, SK Sri Petaling, Avante Intelligence, Arif.my, digitalisasi pendidikan, pendigitalan sekolah, semakan yuran, pembayaran PIBG, portal ibu bapa, inisiatif pendidikan digital',
        'seo_og_site_name' => 'Portal Yuran PIBG SK Sri Petaling',
        'seo_favicon_url' => \App\Models\SiteSetting::faviconUrl(),
    ];

    $seoConfig = \App\Models\SiteSetting::getMany($seoDefaults);
    $seoTitle = filled($title ?? null) ? (string) $title : (string) ($seoConfig['seo_site_title'] ?? $seoDefaults['seo_site_title']);
    $seoDescription = filled($metaDescription ?? null) ? (string) $metaDescription : (string) ($seoConfig['seo_description'] ?? $seoDefaults['seo_description']);
    $seoKeywords = filled($metaKeywords ?? null) ? (string) $metaKeywords : (string) ($seoConfig['seo_keywords'] ?? $seoDefaults['seo_keywords']);
    $seoOgSiteName = (string) ($seoConfig['seo_og_site_name'] ?? $seoDefaults['seo_og_site_name']);
    $seoFaviconUrl = \App\Models\SiteSetting::faviconUrl();
@endphp

<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="keywords" content="{{ $seoKeywords }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $seoOgSiteName }}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">

<link rel="icon" type="image/png" href="{{ $seoFaviconUrl }}?v=5">
<link rel="shortcut icon" type="image/png" href="{{ $seoFaviconUrl }}?v=5">
<link rel="apple-touch-icon" href="{{ $seoFaviconUrl }}?v=5">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
