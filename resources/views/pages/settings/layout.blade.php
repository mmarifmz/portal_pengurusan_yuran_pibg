<style>
    .portal-settings-card {
        border: 1px solid #e5e7eb;
        background: rgba(255, 255, 255, 0.94);
        border-radius: 1rem;
        box-shadow: 0 8px 24px rgba(30, 41, 59, 0.07);
    }

    .portal-settings-nav a {
        display: block;
        border-radius: 0.85rem;
        padding: 0.8rem 0.95rem;
        color: #334155;
        font-weight: 600;
        transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
        border: 1px solid transparent;
    }

    .portal-settings-nav a:hover {
        background: #f3f8f3;
        color: #174a34;
        border-color: rgba(47, 122, 85, 0.16);
    }

    .portal-settings-nav a[aria-current='page'] {
        background: rgba(47, 122, 85, 0.12);
        color: #174a34;
        border-color: rgba(47, 122, 85, 0.18);
    }

    .portal-settings-title {
        color: #174a34;
    }

    .portal-settings-subtitle {
        color: #4b5563;
    }
</style>

<div class="flex items-start gap-6 max-md:flex-col">
    <div class="w-full md:w-[220px] md:flex-none">
        <div class="portal-settings-card p-3">
            <div class="mb-3 px-2">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('Settings') }}</p>
            </div>

            <flux:navlist aria-label="{{ __('Settings') }}" class="portal-settings-nav">
                <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
                @if (! auth()->user()->isParent())
                    <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navlist.item>
                @endif
            </flux:navlist>
        </div>
    </div>

    <div class="min-w-0 flex-1 self-stretch">
        <div class="portal-settings-card p-6 sm:p-8">
            <h2 class="portal-settings-title text-2xl font-bold tracking-tight">{{ $heading ?? '' }}</h2>
            <p class="portal-settings-subtitle mt-2 text-sm sm:text-base">{{ $subheading ?? '' }}</p>

            <div class="mt-6 w-full max-w-none">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
