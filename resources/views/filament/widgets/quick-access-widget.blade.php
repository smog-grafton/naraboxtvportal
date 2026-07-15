<x-filament-widgets::widget class="fi-admin-quick-access-widget">
    <x-filament::section
        heading="Quick Access"
        description="Jump straight into the admin areas you will use most often."
        icon="heroicon-o-bolt"
        icon-color="warning"
    >
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($links as $link)
                <a
                    href="{{ $link['url'] }}"
                    class="group rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900 dark:hover:border-white/20"
                >
                    <div class="flex items-start gap-3">
                        <span class="rounded-lg bg-gray-100 p-2 text-gray-700 dark:bg-white/10 dark:text-white">
                            <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-gray-950 transition group-hover:text-gray-700 dark:text-white">
                                    {{ $link['label'] }}
                                </h3>

                                <x-filament::icon icon="heroicon-o-arrow-up-right" class="h-4 w-4 text-gray-400 transition group-hover:text-gray-700 dark:group-hover:text-white" />
                            </div>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $link['description'] }}
                            </p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
