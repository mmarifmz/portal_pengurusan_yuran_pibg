<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php
    $seoDefaults = [
        'seo_site_title' => 'Jogathon Digital SK Sri Petaling',
        'seo_description' => 'Mini app Jogathon Digital SK Sri Petaling untuk pengurusan peserta, kad kutipan digital dan analitik kempen.',
        'seo_keywords' => 'Jogathon Digital, SK Sri Petaling, Larian Sihat Jogathon, kad kutipan digital, peserta jogathon',
        'seo_og_site_name' => 'Jogathon Digital SK Sri Petaling',
        'seo_favicon_url' => \App\Models\SiteSetting::faviconUrl(),
    ];

    $seoConfig = \App\Models\SiteSetting::getMany($seoDefaults);
    $seoTitle = filled($title ?? null) ? (string) $title : (string) ($seoConfig['seo_site_title'] ?? $seoDefaults['seo_site_title']);
    $seoDescription = filled($metaDescription ?? null) ? (string) $metaDescription : (string) ($seoConfig['seo_description'] ?? $seoDefaults['seo_description']);
    $seoKeywords = filled($metaKeywords ?? null) ? (string) $metaKeywords : (string) ($seoConfig['seo_keywords'] ?? $seoDefaults['seo_keywords']);
    $seoOgSiteName = filled($metaOgSiteName ?? null) ? (string) $metaOgSiteName : (string) ($seoConfig['seo_og_site_name'] ?? $seoDefaults['seo_og_site_name']);
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
<meta name="jogathon-school-logo" content="{{ \App\Models\SiteSetting::schoolLogoUrl() }}">

<link rel="icon" type="image/png" href="{{ $seoFaviconUrl }}?v=5">
<link rel="shortcut icon" type="image/png" href="{{ $seoFaviconUrl }}?v=5">
<link rel="apple-touch-icon" href="{{ $seoFaviconUrl }}?v=5">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
