<?php

namespace Tests\Unit;

use App\Services\PresenceService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PresenceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.presence_store' => 'array']);
        Cache::store('array')->forget('narabox.presence.entries.v1');
    }

    public function test_repeated_heartbeats_from_one_identity_count_once(): void
    {
        $service = app(PresenceService::class);

        $service->heartbeat([
            'presence_id' => 'browser-identity-123',
            'platform' => 'web',
        ]);
        $service->heartbeat([
            'presence_id' => 'browser-identity-123',
            'platform' => 'web',
        ]);

        $snapshot = $service->snapshot();

        $this->assertSame(1, $snapshot['people_online']);
        $this->assertSame(1, $snapshot['guests_online']);
        $this->assertSame(1, $snapshot['active_devices']);
        $this->assertSame(1, $snapshot['platforms']['web']);
    }

    public function test_login_replaces_the_same_device_guest_presence(): void
    {
        $service = app(PresenceService::class);

        $service->heartbeat([
            'presence_id' => 'browser-identity-456',
            'platform' => 'web',
        ]);
        $service->heartbeat([
            'presence_id' => 'browser-identity-456',
            'platform' => 'web',
        ], 42);

        $snapshot = $service->snapshot();

        $this->assertSame(1, $snapshot['people_online']);
        $this->assertSame(1, $snapshot['unique_accounts_online']);
        $this->assertSame(0, $snapshot['guests_online']);
    }
}
