<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->metrics() as $label => $value)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500">{{ $label }}</div>
                <div class="mt-2 text-3xl font-bold">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full text-left text-sm">
            <thead><tr><th class="p-3">Time</th><th class="p-3">Risk</th><th class="p-3">Event</th><th class="p-3">User</th><th class="p-3">Reason</th></tr></thead>
            <tbody>
            @foreach ($this->criticalEvents() as $event)
                <tr class="border-t border-gray-200 dark:border-gray-700"><td class="p-3">{{ $event->occurred_at }}</td><td class="p-3">{{ $event->risk_level }}</td><td class="p-3">{{ $event->event_type }}</td><td class="p-3">{{ $event->user?->email ?? $event->user_id ?? '—' }}</td><td class="p-3">{{ $event->reason }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
