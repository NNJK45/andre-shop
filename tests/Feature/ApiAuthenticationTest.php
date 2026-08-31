<?php

namespace Tests\Feature;

use App\Domain\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receives_an_access_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'André Client',
            'email' => ' CLIENT@EXAMPLE.COM ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'storefront',
            'role' => UserRole::Admin->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'André Client')
            ->assertJsonPath('user.email', 'client@example.com')
            ->assertJsonPath('user.role', UserRole::Customer->value)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', [
            'email' => 'client@example.com',
            'role' => UserRole::Customer->value,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_registration_validates_unique_email_and_password_confirmation(): void
    {
        User::factory()->create(['email' => 'client@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Another Client',
            'email' => 'client@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'client@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => ' CLIENT@EXAMPLE.COM ',
            'password' => 'password123',
            'device_name' => 'mobile',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', UserRole::Customer->value)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'mobile',
        ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'client@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'client@example.com',
            'password' => 'incorrect-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_user_can_view_their_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', UserRole::Customer->value);
    }

    public function test_logout_revokes_only_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current')->plainTextToken;
        $currentTokenId = $user->tokens()->latest('id')->value('id');
        $user->createToken('other');

        $this->withToken($currentToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentTokenId]);
    }

    public function test_authentication_is_required_for_profile_and_logout(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }
}
