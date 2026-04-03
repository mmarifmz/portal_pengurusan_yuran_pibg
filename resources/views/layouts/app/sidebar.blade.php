<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-2xl border border-transparent px-3 py-2 transition hover:border-zinc-200 dark:hover:border-zinc-600" wire:navigate>
                    <img src="{{ asset('images/sksp-logo.png') }}" alt="SK Sri Petaling crest" class="h-10 w-10 rounded-full border border-zinc-200 bg-white p-1 shadow-sm dark:border-zinc-700" />
                    <div class="flex flex-col text-sm font-semibold leading-tight">
                        <span class="text-zinc-900 dark:text-white">Portal Yuran PIBG</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Sekolah Kebangsaan Sri Petaling</span>
                    </div>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid gap-1">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    @if (auth()->user()->isTeacher() || auth()->user()->isPta())
                        <flux:sidebar.item icon="chart-bar" :href="route('teacher.dashboard')" :current="request()->routeIs('teacher.dashboard')" wire:navigate>
                            {{ __('Teacher Dashboard') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="banknotes" :href="route('pta.dashboard')" :current="request()->routeIs('pta.dashboard')" wire:navigate>
                            {{ __('PTA Dashboard') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('students.import.form')" :current="request()->routeIs('students.import.form')" wire:navigate>
                            {{ __('Student import') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('students.family.list')" :current="request()->routeIs('students.family.list')" wire:navigate>
                            {{ __('Family registry') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="list-bullet" :href="route('teacher.records')" :current="request()->routeIs('teacher.records')" wire:navigate>
                            {{ __('Records') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->isParent())
                        <flux:sidebar.item icon="users" :href="route('parent.dashboard')" :current="request()->routeIs('parent.dashboard')" wire:navigate>
                            {{ __('Parent Dashboard') }}
                        </flux:sidebar.item>
                    @endif

                    <flux:sidebar.item icon="magnifying-glass" :href="route('parent.search')" :current="request()->routeIs('parent.search')" wire:navigate>
                        {{ __('Public Parent Search') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="lg:hidden">
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

        @fluxScripts
    </body>
</html>
