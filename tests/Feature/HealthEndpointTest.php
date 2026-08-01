<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_checks_the_application_database_without_an_api_key(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'status' => 'ok',
                'service' => 'narabox-portal',
                'database' => 'reachable',
            ])
            ->assertJsonStructure(['latency_ms', 'checked_at']);
    }
}
