<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperPortalPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_page_requires_auth(): void
    {
        $response = $this->get('/developer');

        $response->assertRedirect('/auth/login');
    }

    public function test_developer_page_renders_for_verified_user(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/developer');

        $response->assertOk()
            ->assertSee('Developer Portal')
            ->assertSee('/api/patients')
            ->assertSee('X-API-Key')
            ->assertSee('fhir/Patient');
    }
}
