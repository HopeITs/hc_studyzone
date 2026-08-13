<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_redirects_unauthenticated_users_to_the_login_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_the_login_page_is_safe_and_non_blocking(): void
    {
        $response = $this->get(route('filament.admin.auth.login'));

        $response
            ->assertOk()
            ->assertSee('name="csrf-token"', escape: false)
            ->assertSee('content="noindex, nofollow, noarchive"', escape: false);

        preg_match_all('/<script\b[^>]*\bsrc=(?:"[^"]+"|\'[^\']+\')[^>]*>/i', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[0]);

        foreach ($matches[0] as $scriptTag) {
            $this->assertMatchesRegularExpression('/\bdefer(?:\s*=\s*(?:"true"|\'true\'|true))?/i', $scriptTag);
        }
    }
}
