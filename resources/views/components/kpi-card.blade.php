@props([
    'title',
    'value',
    'icon' => 'chart-bar',
    'variant' => 'default',
])

@php
    $iconClasses = match ($variant) {
        'danger' => 'text-rose-500',
        default => 'text-emerald-500',
    };

    $borderClass = $variant === 'danger' ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50/70';
@endphp

<div class="rounded-2xl border {{ $borderClass }} p-5 shadow-sm">
    <div class="flex items-center justify-between text-xs uppercase tracking-wider text-zinc-500">
        <span>{{ $title }}</span>
        <span class="rounded-full border px-2 py-0.5 text-[0.6rem] font-semibold {{ $iconClasses }}">•</span>
    </div>
    <div class="mt-4 text-3xl font-semibold text-zinc-900">
        {{ $value }}
    </div>
    <p class="mt-1 text-xs text-zinc-500">
        {{ $slot }}
    </p>
</div>
