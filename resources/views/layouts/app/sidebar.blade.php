<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')

        <style>
            :root {
                --portal-forest: #174a34;
                --portal-green: #2f7a55;
                --portal-gold: #e5b338;
                --portal-ink: #1f2a24;
                --portal-soft: #f3f8f3;
            }

            .portal-shell {
                background:
                    radial-gradient(70rem 34rem at 0% 0%, rgba(47, 122, 85, 0.10), transparent 58%),
                    radial-gradient(70rem 34rem at 100% 0%, rgba(229, 179, 56, 0.12), transparent 55%),
                    linear-gradient(160deg, #f5faf6 0%, #ffffff 40%, #fff8e8 100%);
            }

            .portal-sidebar {
                color: #2b3a32;
            }

            .portal-sidebar :where([data-flux-sidebar-group-heading]) {
                color: #53645a !important;
                font-weight: 700;
            }

            .portal-sidebar :where([data-flux-sidebar-item]) {
                color: #2f3d35 !important;
            }

            .portal-sidebar :where([data-flux-sidebar-item]:hover) {
                color: var(--portal-forest) !important;
                background: var(--portal-soft);
            }

            .portal-sidebar :where([data-current='true']) {
                color: var(--portal-forest) !important;
                background: rgba(47, 122, 85, 0.12) !important;
                box-shadow: inset 0 0 0 1px rgba(47, 122, 85, 0.12);
            }
        </style>
    </head>
    <body class="portal-shell min-h-screen text-[color:var(--portal-ink)] antialiased">
        @php
            $sidebarHomeRoute = auth()->user()?->isParent() ? route('parent.dashboard') : route('dashboard');
        @endphp
        <flux:sidebar sticky collapsible="mobile" class="portal-sidebar border-e border-zinc-200/80 bg-white/85 backdrop-blur-sm">
            <flux:sidebar.header>
                <a href="{{ $sidebarHomeRoute }}" class="flex items-center gap-3 rounded-2xl border border-transparent px-3 py-2 transition hover:border-zinc-200/80 hover:bg-[color:var(--portal-soft)]" wire:navigate>
                    <img src="{{ \App\Models\SiteSetting::schoolLogoUrl() }}" alt="SK Sri Petaling crest" class="h-10 w-10 rounded-full border border-zinc-200 bg-white p-1 shadow-sm" />
                    <div class="flex flex-col text-sm font-semibold leading-tight">
                        <span class="text-[color:var(--portal-forest)]">Portal Yuran PIBG</span>
                        <span class="text-xs text-zinc-500">Sekolah Kebangsaan Sri Petaling</span>
                    </div>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid gap-1">
                    @if (! auth()->user()->isParent())
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                    @endif

                    @if (! auth()->user()->isParent())
                        <flux:sidebar.item icon="calendar" :href="route('school-calendar')" :current="request()->routeIs('school-calendar')" wire:navigate>
                            {{ __('School Calendar') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->canAccessTeacherRecords())
                        <flux:sidebar.item icon="chart-bar" :href="route('teacher.records')" :current="request()->routeIs('teacher.records*')" wire:navigate>
                            {{ __('Student Directory') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="banknotes" :href="route('teacher.finance-accounting')" :current="request()->routeIs('teacher.finance-accounting*')" wire:navigate>
                            {{ __('Finance Accounting') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="funnel" :href="route('system.payment-funnel-monitor.index')" :current="request()->routeIs('system.payment-funnel-monitor.*')" wire:navigate>
                            {{ __('Payment Funnel') }}
                        </flux:sidebar.item>
                    @endif

                    @if (in_array(auth()->user()->role, ['teacher', 'super_teacher', 'system_admin'], true))
                        <flux:sidebar.item icon="device-phone-mobile" :href="route('teacher.family-login-monitor')" :current="request()->routeIs('teacher.family-login-monitor')" wire:navigate>
                            {{ __('Parent Access Log') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->isParent())
                        <flux:sidebar.item icon="users" :href="route('parent.dashboard')" :current="request()->routeIs('parent.dashboard')" wire:navigate>
                            {{ __('Ibu / Bapa & Penjaga') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar" :href="route('school-calendar')" :current="request()->routeIs('school-calendar')" wire:navigate>
                            {{ __('Takwim Sekolah') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clock" :href="route('parent.payments.history')" :current="request()->routeIs('parent.payments.history')" wire:navigate>
                            {{ __('Payment History') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                @if (! auth()->user()->isParent() && (auth()->user()->isSystemAdmin() || auth()->user()->canManageTeacherUsers()))
                    <flux:sidebar.group :heading="__('Platform Config')" class="grid gap-1 mt-2">
                        @if (auth()->user()->isSystemAdmin())
                            <flux:sidebar.item icon="globe-alt" :href="route('system.portal-seo.index')" :current="request()->routeIs('system.portal-seo.*')" wire:navigate>
                                {{ __('Portal Setting') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="beaker" :href="route('system.payment-testers.index')" :current="request()->routeIs('system.payment-testers.*')" wire:navigate>
                                {{ __('Tester Zone') }}
                            </flux:sidebar.item>

                        @endif

                        @if (auth()->user()->canManageTeacherUsers())
                            <flux:sidebar.item icon="user-group" :href="route('super-teacher.teachers.index')" :current="request()->routeIs('super-teacher.teachers.*')" wire:navigate>
                                {{ __('Teachers Room') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif

                @if (! auth()->user()->isParent() && auth()->user()->isSystemAdmin())
                    <flux:sidebar.group :heading="__('System Admin')" class="grid gap-1 mt-2">
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('students.import.form')" :current="request()->routeIs('students.import.form')" wire:navigate>
                            {{ __('Student Import') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-path-rounded-square" :href="route('teacher.reconcile.index')" :current="request()->routeIs('teacher.reconcile.*')" wire:navigate>
                            {{ __('Year Reconcile') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="archive-box" :href="route('system.backups.index')" :current="request()->routeIs('system.backups.*')" wire:navigate>
                            {{ __('Backup DB') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
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
                    const mobileMessages = desktopMessages.map((message) =>
                        String(message).replace('Yuran + Sumbangan Tambahan', 'Yuran + Sumbangan')
                    );

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
