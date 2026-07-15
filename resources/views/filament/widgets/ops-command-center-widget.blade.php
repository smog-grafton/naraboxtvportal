<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Operations Command Center
        </x-slot>

        <x-slot name="description">
            Safe dashboard buttons for playback repair, legacy source recovery, NBX/CDN sync, queues, payment reconciliation, and subscription cleanup.
        </x-slot>

        <div class="flex flex-wrap gap-3">
            {{ $this->diagnoseVideoSourcesAction }}
            {{ $this->restoreLegacySourcesAction }}
            {{ $this->restoreContaboSourcesAction }}
            {{ $this->repairVideoAvailabilityAction }}
            {{ $this->syncDownloadsAction }}
            {{ $this->syncCdnReadinessAction }}
            {{ $this->syncNbxSourcesAction }}
            {{ $this->backfillContaboNbxAction }}
            {{ $this->runQueueAction }}
            {{ $this->restartQueuesAction }}
            {{ $this->reconcilePaymentsAction }}
            {{ $this->expireSubscriptionsAction }}
        </div>

        <x-filament-actions::modals />
    </x-filament::section>
</x-filament-widgets::widget>
