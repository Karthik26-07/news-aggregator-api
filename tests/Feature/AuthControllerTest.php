<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Test successful user registration.
     *
     * @return void
     */
    public function test_register_successfully_creates_user_and_returns_token()
    {
        // Arrange: Fake the Registered event
        Event::fake();

        $registerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123'
        ];

        // Act: Make the POST request to register
        $response = $this->postJson('/api/register', $registerData);

        // Assert: Check the response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'user' => [
                        'x_id',
                        'name',
                        'email'
                    ],
                ],
                'status',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User registered successfully. Please verify your email to log in.',
                'status' => 200,
            ]);

        // // Assert: Check the user was created in the database
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Assert: Verify the password is hashed
        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));

        // Assert: Verify the token was created
        $this->assertNotNull($user->tokens()->first());

        // Assert: Verify the Registered event was dispatched
        Event::assertDispatched(Registered::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    /**
     * Test registration fails with invalid data.
     *
     * @return void
     */
    // public function test_register_fails_with_invalid_data()
    // {
    //     // Arrange: Invalid data (missing required fields, mismatched passwords)
    //     $invalidData = [
    //         'name' => 'John Doe',
    //         'email' => 'john@example.com',
    //         'password' => 'karthik@1234', // Too short
    //         'password_confirmation' => 'different',
    //     ];

    //     // Act: Make the POST request
    //     $response = $this->postJson('/api/register', $invalidData);

    //     // Assert: Check the validation error response
    //     $response->assertStatus(422)
    //         ->assertJsonStructure([
    //             'success',
    //             'message',
    //             'data' => [
    //                 'password',
    //             ],
    //             'status',
    //         ])
    //         ->assertJson([
    //             'success' => false,
    //             'message' => 'Validation failed',
    //             'status' => 422,
    //         ])
    //         ->assertJsonFragment([
    //             'password' => ['The password field must be at least 8 characters.', 'The password field confirmation does not match.'],
    //         ]);

    //     // Assert: No user was created
    //     $this->assertDatabaseMissing('users', [
    //         'email' => 'john@example.com',
    //     ]);
    // }

    /**
     * Test registration fails with duplicate email.
     *
     * @return void
     */
    public function test_register_fails_with_duplicate_email()
    {
        // Arrange: Create an existing user
        User::factory()->create([
            'email' => 'john@example.com',
        ]);

        $registerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com', // Duplicate email
            'password' => 'password123'
        ];

        // Act: Make the POST request
        $response = $this->postJson('/api/register', $registerData);

        // Assert: Check the validation error response
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
                'status' => 422,
            ])
            ->assertJsonFragment([
                'email' => ['The email has already been taken.'],
            ]);

        // Assert: Only one user exists
        $this->assertEquals(1, User::count());
    }

    public function test_verify_email()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $hash = sha1($user->getEmailForVerification());

        $response = $this->get("/api/email/verify/{$user->id}/{$hash}");

        $response->assertStatus(200);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_login_user()
    {
        $user = User::factory()->create([
            'email' => 'user@gmail.com',
            'password' => 'password123',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@gmail.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'status',
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_at',
                ],
            ])->assertJson([
                    'success' => true,
                    'message' => 'Login successful',
                    'status' => 200
                ]);
    }

    public function test_logout_user()
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
    }

    public function test_send_password_reset_link()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/password/email', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset token generated. Use this token to reset your password.',
            ]);
    }

    public function test_reset_password()
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset successfully',
            ]);
    }
}
