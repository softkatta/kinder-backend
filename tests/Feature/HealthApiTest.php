<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthApiTest extends TestCase
{
    public function test_health_endpoint_is_running(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()->assertJsonPath('data.status', 'ok');
    }
}
