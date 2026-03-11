<x-filament-panels::page>
    @php($urls = $this->getCallbackUrls())

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Callback URLs
            </x-slot>
            <x-slot name="description">
                Set these URLs in PawaPay Dashboard -> System configuration -> Callback URLs before generating or rotating your API token.
            </x-slot>

            <div class="space-y-4">
                <div>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Deposits callback URL</p>
                    <code class="block mt-1 rounded bg-gray-100 dark:bg-gray-900 px-3 py-2 text-sm">{{ $urls['deposit'] }}</code>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Refunds callback URL</p>
                    <code class="block mt-1 rounded bg-gray-100 dark:bg-gray-900 px-3 py-2 text-sm">{{ $urls['refund'] }}</code>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Environment Notes
            </x-slot>

            <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <li>These URLs are generated from <code>APP_URL</code>, so they automatically change per environment (local, staging, production).</li>
                <li>Set sandbox credentials using <code>PAWAPAY_BASE_URL=https://api.sandbox.pawapay.io</code>.</li>
                <li>Set production credentials using <code>PAWAPAY_BASE_URL=https://api.pawapay.io</code>.</li>
                <li>Keep <code>PAWAPAY_API_TOKEN</code> in server environment variables only. Never expose it to Next.js.</li>
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>

