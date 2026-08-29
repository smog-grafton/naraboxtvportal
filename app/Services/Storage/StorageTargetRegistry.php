<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;

/**
 * Central lookup for the logical storage targets Portal knows about.
 * NBX has its own equivalent registry backed by its own config/env — the
 * two only need to agree on the logical keys, never on credentials.
 */
class StorageTargetRegistry
{
    /** @var array<string, StorageTarget>|null */
    private ?array $targets = null;

    /**
     * @return array<string, StorageTarget>
     */
    public function all(): array
    {
        if ($this->targets !== null) {
            return $this->targets;
        }

        $this->targets = [];

        foreach ((array) config('storage_targets.targets', []) as $key => $targetConfig) {
            $targetConfig = (array) $targetConfig;
            if ($key === $this->legacyKey()) {
                $targetConfig = $this->withLiveLegacyOverrides($targetConfig);
            }
            $this->targets[$key] = StorageTarget::fromConfig($key, $targetConfig);
        }

        return $this->targets;
    }

    /**
     * The legacy target's dynamic fields historically read
     * config('services.contabo_object_storage.*') LIVE on every call (never
     * cached), so runtime config mutations (tests, admin tooling) took
     * effect immediately. Preserve that: prefer the live legacy config over
     * the CONTABO_NBX_* value, UNLESS an operator explicitly set a distinct
     * CONTABO_NBX_* env var (checked via env(), which — unlike config() —
     * is not mutated at runtime, so this only fires for real operator intent).
     */
    private function withLiveLegacyOverrides(array $targetConfig): array
    {
        // "enabled" is intentionally NOT live-linked: it's a boot-time
        // operator decision (CONTABO_NBX_ENABLED / CONTABO_OBJECT_STORAGE_
        // ENABLED), and unlike the identity/URL fields below nothing in the
        // app needs to flip it at runtime — doing so would also make this
        // registry's own config(['storage_targets....enabled' => ...])
        // overrides (used in tests) unpredictably win or lose depending on
        // which config key happened to be touched last.
        $fields = [
            'disk' => ['env' => 'CONTABO_NBX_DISK', 'legacy' => 'services.contabo_object_storage.disk'],
            'bucket' => ['env' => 'CONTABO_NBX_BUCKET', 'legacy' => 'services.contabo_object_storage.bucket'],
            'endpoint' => ['env' => 'CONTABO_NBX_ENDPOINT', 'legacy' => 'services.contabo_object_storage.endpoint'],
            'public_url' => ['env' => 'CONTABO_NBX_PUBLIC_URL', 'legacy' => 'services.contabo_object_storage.public_url'],
            'path_prefix' => ['env' => 'CONTABO_NBX_PATH_PREFIX', 'legacy' => 'services.contabo_object_storage.path_prefix'],
        ];

        foreach ($fields as $field => $source) {
            if (env($source['env']) !== null) {
                continue;
            }

            $liveValue = config($source['legacy']);
            if ($liveValue !== null && $liveValue !== '') {
                $targetConfig[$field] = $liveValue;
            }
        }

        return $targetConfig;
    }

    /**
     * @return array<string, StorageTarget>
     */
    public function enabled(): array
    {
        return array_filter($this->all(), fn (StorageTarget $target) => $target->enabled);
    }

    public function find(string $key): ?StorageTarget
    {
        return $this->all()[$key] ?? null;
    }

    public function findOrFail(string $key): StorageTarget
    {
        $target = $this->find($key);

        if ($target === null) {
            throw new StorageTargetException("Unknown storage target key: [{$key}].");
        }

        return $target;
    }

    public function legacyKey(): string
    {
        return (string) config('storage_targets.legacy_target_key', 'contabo_nbx');
    }

    public function legacyTarget(): StorageTarget
    {
        return $this->findOrFail($this->legacyKey());
    }

    public function isAutoKey(?string $key): bool
    {
        return $key === null || $key === '' || $key === 'auto';
    }

    /**
     * Resolve any incoming key (including legacy literals like "contabo",
     * null, or "auto") down to a concrete stored key, WITHOUT performing
     * automatic selection. Use AutomaticStorageSelector for "auto".
     */
    public function normalizeStoredKey(?string $key): string
    {
        if ($key === null || $key === '' || $key === 'contabo') {
            return $this->legacyKey();
        }

        return $key;
    }

    public function selectOptions(): array
    {
        $options = ['auto' => 'Automatic — Recommended'];

        foreach ($this->all() as $target) {
            $options[$target->key] = $target->label;
        }

        return $options;
    }

    public function disk(StorageTarget $target)
    {
        return Storage::disk($target->disk);
    }
}
