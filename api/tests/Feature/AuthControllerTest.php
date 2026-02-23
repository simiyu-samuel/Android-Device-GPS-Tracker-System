<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_issues_token_on_valid_login()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email']
            ])
            ->assertJsonFragment([
                'email' => 'test@example.com',
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    /** @test */
    public function it_returns_401_for_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment([
                'message' => 'The provided credentials are incorrect.',
            ]);
    }

    /** @test */
    public function it_returns_401_for_non_existent_user()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment([
                'message' => 'The provided credentials are incorrect.',
            ]);
    }

    /** @test */
    public function it_validates_required_fields_on_login()
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /** @test */
    public function it_validates_email_format_on_login()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_revokes_token_on_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Logged out successfully',
            ]);

        // Verify token count decreased
        $this->assertEquals(0, $user->tokens()->count());
    }

    /** @test */
    public function it_requires_authentication_for_logout()
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_authenticated_user_information()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email']
            ])
            ->assertJsonFragment([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_me_endpoint()
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_does_not_expose_password_in_user_response()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }

    /** @test */
    public function it_allows_multiple_tokens_per_user()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Login twice to get two tokens
        $response1 = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response2 = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token1 = $response1->json('token');
        $token2 = $response2->json('token');

        $this->assertNotEquals($token1, $token2);

        // Both tokens should work
        $verify1 = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->getJson('/api/auth/me');
        $verify1->assertStatus(200);

        $verify2 = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson('/api/auth/me');
        $verify2->assertStatus(200);
    }

    /** @test */
    public function it_only_revokes_current_token_on_logout()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Create two tokens
        $token1 = $user->createToken('auth-token-1')->plainTextToken;
        $token2 = $user->createToken('auth-token-2')->plainTextToken;

        $this->assertEquals(2, $user->tokens()->count());

        // Logout with token1
        $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/auth/logout');

        // Should have 1 token remaining
        $this->assertEquals(1, $user->fresh()->tokens()->count());

        // Token2 should still work
        $verify2 = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson('/api/auth/me');
        $verify2->assertStatus(200);
    }
}
