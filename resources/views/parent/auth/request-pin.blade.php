<x-layouts::auth :title="__('Parent Login (Phone + TAC)')">
    <div class="flex flex-col gap-6">
           
    <x-auth-header :title="__('Parent login with TAC')" :description="__('Enter your phone number to receive a TAC code via WhatsApp (email backup where available).')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('parent.login.request') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="phone"
                :label="__('Phone number')"
                :value="old('phone')"
                type="text"
                required
                autofocus
                autocomplete="tel"
                placeholder="0123456789"
            />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full">
                    {{ __('Send TAC') }}
                </flux:button>
            </div>
        </form>

        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            <flux:link :href="route('login')" wire:navigate>{{ __('Teacher/PTA login') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>