<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="font-semibold text-gray-950 dark:text-white">Recreate media lost with NBX's database</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                If NBX's database was lost and rebuilt from Contabo storage, this replays Portal's own record of what
                it previously sent to NBX. Run <strong>Re-discover</strong> first — it is cheap and only re-links
                sources against whatever NBX already recovered from storage. Only use <strong>Re-fetch &amp;
                Re-transcode</strong> for what Re-discover could not match; it re-downloads and re-encodes from the
                original source URL, which costs real bandwidth and time.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-950 dark:text-white">Tier 1 — Re-discover</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ number_format($tier1Eligible) }} video source(s) show prior NBX involvement and are eligible.
                        </p>
                        @if ($latestTier1)
                            <p class="mt-2 text-xs text-gray-500">
                                Last run ({{ $latestTier1['status'] }}): {{ $latestTier1['matched'] ?? 0 }} matched,
                                {{ $latestTier1['unmatched'] ?? 0 }} unmatched, {{ $latestTier1['failed'] ?? 0 }} errored
                                &middot; {{ ($latestTier1['matched'] ?? 0) + ($latestTier1['unmatched'] ?? 0) + ($latestTier1['failed'] ?? 0) }}
                                / {{ $latestTier1['total'] ?? 0 }} processed
                            </p>
                        @endif
                    </div>
                    <x-filament::button
                        wire:click="startTier1"
                        wire:loading.attr="disabled"
                        wire:target="startTier1"
                        :disabled="$tier1Eligible === 0"
                    >
                        <span wire:loading.remove wire:target="startTier1">Re-discover from NBX</span>
                        <span wire:loading wire:target="startTier1">Queuing…</span>
                    </x-filament::button>
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-950 dark:text-white">Tier 2 — Re-fetch &amp; Re-transcode</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ number_format($tier2Eligible) }} video source(s) that Re-discover already tried and could not match.
                        </p>
                        @if ($latestTier2)
                            <p class="mt-2 text-xs text-gray-500">
                                Last run ({{ $latestTier2['status'] }}): {{ $latestTier2['recreated'] ?? 0 }} recreated,
                                {{ $latestTier2['failed'] ?? 0 }} errored
                                &middot; {{ ($latestTier2['recreated'] ?? 0) + ($latestTier2['failed'] ?? 0) }}
                                / {{ $latestTier2['total'] ?? 0 }} processed
                            </p>
                        @endif
                    </div>
                    <x-filament::button
                        wire:click="startTier2"
                        wire:loading.attr="disabled"
                        wire:target="startTier2"
                        wire:confirm="Re-fetch and re-transcode {{ $tier2Eligible }} video(s) from their original source URL? This costs real bandwidth and NBX compute and can take a long time."
                        color="danger"
                        :disabled="$tier2Eligible === 0"
                    >
                        <span wire:loading.remove wire:target="startTier2">Re-fetch &amp; Re-transcode remaining</span>
                        <span wire:loading wire:target="startTier2">Queuing…</span>
                    </x-filament::button>
                </div>
            </div>
        </div>

        <div>
            <x-filament::button wire:click="refreshCounts" color="gray" size="sm">Refresh</x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
