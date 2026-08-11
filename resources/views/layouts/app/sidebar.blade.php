<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')

        <style>
            :root {
                --jogathon-forest: #174a34;
                --jogathon-green: #2f7a55;
                --jogathon-gold: #e5b338;
                --jogathon-ink: #1f2a24;
                --jogathon-soft: #f3f8f3;
            }

            .jogathon-shell {
                background:
                    radial-gradient(70rem 34rem at 0% 0%, rgba(47, 122, 85, 0.10), transparent 58%),
                    radial-gradient(70rem 34rem at 100% 0%, rgba(229, 179, 56, 0.12), transparent 55%),
                    linear-gradient(160deg, #f5faf6 0%, #ffffff 40%, #fff8e8 100%);
            }

            .jogathon-sidebar {
                color: #2b3a32;
            }

            .jogathon-sidebar :where([data-flux-sidebar-group-heading]) {
                color: #53645a !important;
                font-weight: 700;
            }

            .jogathon-sidebar :where([data-flux-sidebar-item]) {
                color: #2f3d35 !important;
            }

            .jogathon-sidebar :where([data-flux-sidebar-item]:hover) {
                color: var(--jogathon-forest) !important;
                background: var(--jogathon-soft);
            }

            .jogathon-sidebar :where([data-current='true']) {
                color: var(--jogathon-forest) !important;
                background: rgba(47, 122, 85, 0.12) !important;
                box-shadow: inset 0 0 0 1px rgba(47, 122, 85, 0.12);
            }
        </style>

</head>
    <body class="jogathon-shell min-h-screen text-[color:var(--jogathon-ink)] antialiased">
        @php
            $currentUser = auth()->user();
            $sidebarHomeRoute = $currentUser?->isSystemAdmin()
                ? route('system.jogathon.campaigns.index')
                : ($currentUser?->hasAnyRole(['teacher', 'super_teacher'])
                    ? route('teacher.jogathon.cards.index')
                    : route('home'));
        @endphp
        <flux:sidebar sticky collapsible="mobile" class="jogathon-sidebar border-e border-zinc-200/80 bg-white/85 backdrop-blur-sm">
            <flux:sidebar.header>
                <a href="{{ $sidebarHomeRoute }}" class="flex items-center gap-3 rounded-2xl border border-transparent px-3 py-2 transition hover:border-zinc-200/80 hover:bg-[color:var(--jogathon-soft)]" wire:navigate>
                    <img src="{{ \App\Models\SiteSetting::schoolLogoUrl() }}" alt="SK Sri Petaling crest" class="h-10 w-10 rounded-full border border-zinc-200 bg-white p-1 shadow-sm" />
                    <div class="flex flex-col text-sm font-semibold leading-tight">
                        <span class="text-[color:var(--jogathon-forest)]">Jogathon Digital</span>
                        <span class="text-xs text-zinc-500">SK Sri Petaling</span>
                    </div>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Jogathon')" class="grid gap-1">
                    <flux:sidebar.item icon="home" :href="route('home')" :current="request()->routeIs('home') || request()->routeIs('jogathon.public.*')" wire:navigate>
                        {{ __('Laman Kempen') }}
                    </flux:sidebar.item>

                    @can('enterJogathonPhysicalCollections')
                        <flux:sidebar.item icon="qr-code" :href="route('teacher.jogathon.cards.index')" :current="request()->routeIs('teacher.jogathon.cards.*')" wire:navigate>
                            {{ __('Kad Jogathon') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('manageJogathonCampaigns')
                        <flux:sidebar.item icon="flag" :href="route('system.jogathon.campaigns.index')" :current="request()->routeIs('system.jogathon.*')" wire:navigate>
                            {{ __('Admin Jogathon') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="border-b border-zinc-200/80 bg-white/85 backdrop-blur-sm lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}
        <x-toaster-hub />
        @if (($globalRecentPaymentToasts ?? collect())->isNotEmpty())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const desktopMessages = @js(($globalRecentPaymentToasts ?? collect())->values()->all());
                    const mobileMessages = desktopMessages;

                    const isMobile = window.matchMedia('(max-width: 640px)').matches;
                    const messages = isMobile ? mobileMessages : desktopMessages;
                    if (!Array.isArray(messages) || messages.length === 0) {
                        return;
                    }

                    const pushToast = (message) => {
                        if (window.Toaster && typeof window.Toaster.success === 'function') {
                            window.Toaster.success(message);
                        }
                    };

                    const shouldPause = () => {
                        if (document.hidden) {
                            return true;
                        }

                        const active = document.activeElement;
                        if (!active) {
                            return false;
                        }

                        return ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName);
                    };

                    let index = 0;
                    const intervalMs = isMobile ? 9000 : 5500;
                    const initialDelayMs = isMobile ? 1200 : 500;

                    const cycle = () => {
                        if (shouldPause()) {
                            return;
                        }

                        pushToast(messages[index]);
                        index = (index + 1) % messages.length;
                    };

                    setTimeout(() => {
                        cycle();
                        setInterval(cycle, intervalMs);
                    }, initialDelayMs);
                });
            </script>
        @endif
        @stack('scripts')
        @fluxScripts

</body>
</html>
