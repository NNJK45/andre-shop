<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_login_is_gracefully_unavailable_without_credentials(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get('/auth/google')->assertRedirect('/#google_error=google_not_configured');
    }

    public function test_google_callback_creates_a_customer_and_issues_a_token(): void
    {
        $googleUser = (new GoogleUser)->map([
            'id' => 'google-user-42',
            'name' => 'Client Google',
            'email' => 'client.google@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ]);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirectContains('/#google_token=');
        $this->assertDatabaseHas('users', [
            'email' => 'client.google@example.com',
            'google_id' => 'google-user-42',
        ]);
        $this->assertSame(1, User::firstOrFail()->tokens()->count());
    }
}
