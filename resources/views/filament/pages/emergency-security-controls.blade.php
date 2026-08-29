<x-filament-panels::page>
    @if ($controls['lockdown_mode'] ?? false)
        <div class="rounded-xl bg-danger-600 p-4 font-semibold text-white">LOCKDOWN MODE IS ACTIVE. Registration and payment initiation are blocked; Filament remains available.</div>
    @endif
    <form wire:submit="save" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (\App\Services\RuntimeSecuritySettings::DEFAULTS as $key => $default)
                <label class="flex items-center justify-between rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <span>{{ str($key)->replace('_', ' ')->title() }}</span>
                    <input type="checkbox" wire:model="controls.{{ $key }}" class="rounded border-gray-300 text-primary-600">
                </label>
            @endforeach
        </div>
        <x-filament::button type="submit" color="danger" wire:confirm="Apply these server-side controls immediately?">Apply Emergency Controls</x-filament::button>
    </form>
</x-filament-panels::page>
