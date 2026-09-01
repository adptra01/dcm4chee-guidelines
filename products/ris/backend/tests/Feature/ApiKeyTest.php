<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_open_when_keys_empty(): void
    {
        putenv('RIS_API_KEYS=');

        $this->getJson('/api/patients')->assertOk();
    }

    public function test_api_rejects_missing_or_wrong_key(): void
    {
        putenv('RIS_API_KEYS=supersecret-key');

        $this->getJson('/api/patients')->assertStatus(401)->assertJson(['message' => 'Invalid API key']);
        $this->getJson('/api/patients', ['X-API-Key' => 'wrong'])->assertStatus(401);
    }

    public function test_api_accepts_valid_key(): void
    {
        putenv('RIS_API_KEYS=supersecret-key');

        $this->getJson('/api/patients', ['X-API-Key' => 'supersecret-key'])->assertOk();
    }

    protected function tearDown(): void
    {
        putenv('RIS_API_KEYS');
        parent::tearDown();
    }
}
