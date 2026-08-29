<?php

namespace Tests\Feature\Storage;

use App\Services\ContaboObjectStorageService;
use App\Services\Storage\AutomaticStorageSelector;
use App\Services\Storage\StorageTargetException;
use App\Services\Storage\StorageTargetRegistry;
use App\Services\Storage\StorageUsageService;
use Tests\TestCase;

class StorageTargetSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'storage_targets.targets.contabo_nbx' => [
                'label' => 'NaraBox Legacy Storage — nbx',
                'provider' => 'contabo',
                'disk' => 'contabo_nbx',
                'endpoint' => 'https://usc1.contabostorage.com',
                'region' => 'US-central',
                'bucket' => 'nbx',
                'public_url' => 'https://usc1.contabostorage.com/tenant:nbx',
                'path_prefix' => 'videos',
                'enabled' => true,
                'writable' => true,
                'priority' => 20,
                'capacity_bytes' => 536870912000, // 500GB
                'reserve_percent' => 10,
                'known_used_bytes' => 471073193164, // ~438.66GB (~87.7%)
                'known_used_at' => now()->toIso8601String(),
            ],
            'storage_targets.targets.contabo_nb_nbx' => [
                'label' => 'NaraBox Storage 2 — nb-nbx',
                'provider' => 'contabo',
                'disk' => 'contabo_nb_nbx',
                'endpoint' => 'https://usc1.contabostorage.com',
                'region' => 'US-central',
                'bucket' => 'nb-nbx',
                'public_url' => 'https://usc1.contabostorage.com/nb-nbx',
                'path_prefix' => 'videos',
                'enabled' => true,
                'writable' => true,
                'priority' => 100,
                'capacity_bytes' => 536870912000,
                'reserve_percent' => 10,
                'known_used_bytes' => 0,
                'known_used_at' => now()->toIso8601String(),
            ],
            'storage_targets.targets.r2_nbx' => [
                'label' => 'Cloudflare R2 — nbx',
                'provider' => 'cloudflare_r2',
                'disk' => 'r2',
                'endpoint' => 'https://e31bdccee36e2432baee084144f9c6ae.r2.cloudflarestorage.com',
                'region' => 'auto',
                'bucket' => 'nbx',
                'public_url' => 'https://nbxgen.naraboxtv.com',
                'path_prefix' => 'videos',
                'enabled' => false,
                'writable' => true,
                'priority' => 200,
                'capacity_bytes' => 10995116277760,
                'reserve_percent' => 5,
                'known_used_bytes' => 0,
                'known_used_at' => now()->toIso8601String(),
            ],
            'storage_targets.legacy_target_key' => 'contabo_nbx',
        ]);
    }

    public function test_missing_target_normalizes_to_legacy_contabo_nbx(): void
    {
        $registry = app(StorageTargetRegistry::class);

        $this->assertSame('contabo_nbx', $registry->normalizeStoredKey(null));
        $this->assertSame('contabo_nbx', $registry->normalizeStoredKey(''));
        $this->assertSame('contabo_nbx', $registry->normalizeStoredKey('contabo'));
        $this->assertSame('contabo_nb_nbx', $registry->normalizeStoredKey('contabo_nb_nbx'));
    }

    public function test_explicit_contabo_nbx_resolves_to_nbx_bucket(): void
    {
        $selector = app(AutomaticStorageSelector::class);
        $resolution = $selector->resolveExplicit('contabo_nbx', 1000);

        $this->assertSame('nbx', $resolution['target']->bucket);
        $this->assertSame('contabo_nbx', $resolution['target']->key);
    }

    public function test_explicit_contabo_nb_nbx_resolves_to_nb_nbx_bucket(): void
    {
        $selector = app(AutomaticStorageSelector::class);
        $resolution = $selector->resolveExplicit('contabo_nb_nbx', 1000);

        $this->assertSame('nb-nbx', $resolution['target']->bucket);
        $this->assertSame('contabo_nb_nbx', $resolution['target']->key);
    }

    public function test_automatic_selection_prefers_r2_when_enabled(): void
    {
        config(['storage_targets.targets.r2_nbx.enabled' => true]);

        $selector = app(AutomaticStorageSelector::class);
        $resolution = $selector->resolveAutomatic(1_000_000);

        $this->assertSame('r2_nbx', $resolution['target']->key);
        $this->assertSame('cloudflare_r2', $resolution['target']->provider);
        $this->assertSame('nbx', $resolution['target']->bucket);
        $this->assertTrue($resolution['auto']);
    }

    public function test_automatic_selection_prefers_nb_nbx_while_nbx_is_near_soft_limit(): void
    {
        $selector = app(AutomaticStorageSelector::class);
        $resolution = $selector->resolveAutomatic(1_000_000);

        $this->assertSame('contabo_nb_nbx', $resolution['target']->key);
        $this->assertTrue($resolution['auto']);
    }

    public function test_disabled_target_is_skipped_by_automatic_selection(): void
    {
        config(['storage_targets.targets.contabo_nb_nbx.enabled' => false]);

        // nbx is at ~87.7%, under its 90% soft limit, so it remains selectable
        // once nb-nbx is disabled.
        $selector = app(AutomaticStorageSelector::class);
        $resolution = $selector->resolveAutomatic(1_000_000);

        $this->assertSame('contabo_nbx', $resolution['target']->key);
    }

    public function test_disabled_target_cannot_be_selected_explicitly(): void
    {
        config(['storage_targets.targets.contabo_nb_nbx.enabled' => false]);

        $this->expectException(StorageTargetException::class);
        app(AutomaticStorageSelector::class)->resolveExplicit('contabo_nb_nbx', 1000);
    }

    public function test_insufficient_capacity_fails_before_processing_begins(): void
    {
        // Both targets nearly full: nbx at 87.7%, nb-nbx artificially filled.
        config(['storage_targets.targets.contabo_nb_nbx.known_used_bytes' => 500000000000]);

        $this->expectException(StorageTargetException::class);
        app(AutomaticStorageSelector::class)->resolveAutomatic(50_000_000_000); // 50GB requested
    }

    public function test_non_privileged_manual_selection_past_soft_cap_is_blocked(): void
    {
        // Push nbx from ~87.7% to ~92% (past its 90% soft limit).
        config(['storage_targets.targets.contabo_nbx.known_used_bytes' => 494089999360]);

        $this->expectException(StorageTargetException::class);
        app(AutomaticStorageSelector::class)->resolveExplicit('contabo_nbx', 1000);
    }

    public function test_privileged_override_proceeds_past_soft_cap_with_a_warning(): void
    {
        config(['storage_targets.targets.contabo_nbx.known_used_bytes' => 494089999360]);

        $resolution = app(AutomaticStorageSelector::class)->resolveExplicit('contabo_nbx', 1000, privileged: true);

        $this->assertNotNull($resolution['warning']);
        $this->assertStringContainsString('overrode', $resolution['warning']);
        $this->assertSame('contabo_nbx', $resolution['target']->key);
    }

    public function test_contabo_service_resolves_bucket_per_target(): void
    {
        $legacy = app(ContaboObjectStorageService::class);
        $this->assertSame('contabo_nbx', $legacy->targetKey());
        $this->assertSame('nbx', $legacy->bucket());

        $secondary = $legacy->forTarget('contabo_nb_nbx');
        $this->assertSame('contabo_nb_nbx', $secondary->targetKey());
        $this->assertSame('nb-nbx', $secondary->bucket());

        // The original instance is unaffected by forTarget() on the clone.
        $this->assertSame('nbx', $legacy->bucket());
    }

    public function test_usage_service_tracks_incremental_uploads_between_snapshots(): void
    {
        $registry = app(StorageTargetRegistry::class);
        $usage = app(StorageUsageService::class);
        $target = $registry->findOrFail('contabo_nb_nbx');

        $before = $usage->usageFor($target)['used_bytes'];
        $usage->recordUpload('contabo_nb_nbx', 5_000_000);
        $after = $usage->usageFor($target)['used_bytes'];

        $this->assertSame($before + 5_000_000, $after);
    }

    public function test_adding_a_third_target_requires_no_schema_change(): void
    {
        config(['storage_targets.targets.contabo_third' => [
            'label' => 'Third Storage',
            'provider' => 'contabo',
            'disk' => 'contabo_third',
            'endpoint' => 'https://usc1.contabostorage.com',
            'region' => 'US-central',
            'bucket' => 'third',
            'public_url' => 'https://usc1.contabostorage.com/third',
            'path_prefix' => 'videos',
            'enabled' => true,
            'writable' => true,
            'priority' => 50,
            'capacity_bytes' => 100000000000,
            'reserve_percent' => 10,
            'known_used_bytes' => 0,
            'known_used_at' => null,
        ]]);

        $registry = app(StorageTargetRegistry::class);
        $this->assertArrayHasKey('contabo_third', $registry->all());
        $this->assertSame('third', $registry->find('contabo_third')->bucket);
    }
}
