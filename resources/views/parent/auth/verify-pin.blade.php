<x-layouts::auth :title="__('Verify TAC')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between gap-4">
        <x-auth-header :title="__('Enter TAC PIN')" :description="__('A 6-digit TAC has been sent to your WhatsApp number.')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <div class="rounded-lg border border-zinc-200 p-3 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
            {{ __('Phone:') }} <span class="font-semibold">{{ $phone }}</span>
            @if ($debugCode)
                <p class="mt-2 text-amber-600 dark:text-amber-300">Dev code: {{ $debugCode }}</p>
            @endif
        </div>

        <form method="POST" action="{{ route('parent.login.verify.submit') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="pin"
                :label="__('TAC PIN')"
                type="text"
                required
                autofocus
                inputmode="numeric"
                maxlength="6"
                placeholder="123456"
            />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full">
                    {{ __('Verify and Login') }}
                </flux:button>
            </div>
        </form>

        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            <flux:link :href="route('parent.login.form')">{{ __('Use another phone number') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>