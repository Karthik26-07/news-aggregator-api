<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limiter before each test
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
    public function test_register_fails_with_invalid_data()
    {
        // Arrange: Invalid data (missing required fields, mismatched passwords)
        $invalidData = [
            'name' => '',
            'email' => 'john@example.com',
            'password' => 'karth', // Too short
        ];

        // Act: Make the POST request
        $response = $this->postJson('/api/register', $invalidData);

        // Assert: Check the validation error response
        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'name',
                    'password'
                ],
                'status',
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
                'status' => 422,
            ])
            ->assertJsonFragment(data: [
                'name' => ['Name is required'],
                'password' => ['Password must be at least 8 characters long']
            ]);

        // Assert: No user was created
        $this->assertDatabaseMissing('users', [
            'email' => 'john@example.com',
        ]);
    }

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

    public function test_verify_registered_email()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $hash = sha1($user->getEmailForVerification());

        $response = $this->get("/api/email/verify/{$user->id}/{$hash}");

        $response->assertStatus(200);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_login_user_return_access_token()
    {
        User::factory()->create([
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

    public function test_login_fails_with_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'user@gmail.com',
            'password' => 'password123',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@gmail.com',
            'password' => 'password567',
        ]);

        $response->assertStatus(401)
            ->assertJsonStructure([
                'success',
                'message',
                'status',
            ])->assertJson([
                    'success' => false,
                    'message' => 'The provided credentials are incorrect.',
                    'status' => 401
                ]);
    }

    public function test_login_fails_with_unverified_email()
    {
        User::factory()->create([
            'email' => 'user1234@gmail.com',
            'password' => 'password123',
            'email_verified_at' => null
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user1234@gmail.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonStructure([
                'success',
                'message',
                'status',
            ])->assertJson([
                    'success' => false,
                    'message' => 'Please verify your email address before logging in.',
                    'status' => 403
                ]);
    }

    public function test_to_many_requests_for_login()
    {

        User::factory()->create([
            'email' => 'user@gmail.com',
            'password' => 'password123',
            'email_verified_at' => now(),
        ]);

        for ($i = 0; $i < 2; $i++) {
            # code...
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

        $response = $this->postJson('/api/login', [
            'email' => 'user@gmail.com',
            'password' => 'password123',
        ])->assertJson(value: [
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                    'status' => 429
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

    public function test_send_password_reset_link_fails_with_unknown_email()
    {
        User::factory()->create(['email' => 'user1234@gmail.com']);

        $response = $this->postJson('/api/password/email', [
            'email' => 'user90@gmail.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No user found with this email address.',
                'status' => 404
            ]);
    }

    public function test_password_reset_link_fails_with_unverified_email()
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->postJson('/api/password/email', [
            'email' => $user->email,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Please verify your email address to reset password.',
                'status' => 403
            ]);
    }

    public function test_reset_password_success()
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

    public function test_reset_password_fails_with_unknown_email()
    {
        $user = User::factory()->create(['email' => 'user123@gmail.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'email' => 'user45@gmail.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => "We can't find a user with that email address.",
                'status' => 400
            ]);
    }
    // public function test_reset_password_fails_with_password_mismatch()
    // {
    //     $user = User::factory()->create();
    //     $token = Password::createToken($user);

    //     $response = $this->postJson('/api/password/reset', [
    //         'email' => $user->email,
    //         'token' => $token,
    //         'password' => 'newpassword1234',
    //         'password_confirmation' => 'newpassword12345',
    //     ]);

    //     $response->assertStatus(422)
    //         ->assertJson([
    //             'success' => false,
    //             'message' => "Validation failed",
    //             'status' => 422,
    //             'data' => ['password' => ['The password confirmation does not match.']]
    //         ]);
    // }

    public function test_reset_password_fails_with_invalid_data()
    {
        $response = $this->postJson('/api/password/reset', [
            'email' => 'user21.com',
            'token' => '',
            'password' => 'newpa',
            'password_confirmation' => 'newpassword12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'status',
                'data' => [
                    'token',
                    'email',
                    'password'
                ]
            ])
            ->assertJson([
                'success' => false,
                'message' => "Validation failed",
                'status' => 422,
            ]);
    }

    public function test_password_reset_fails_with_invalid_token()
    {
        $user = User::factory()->create();
        Password::createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'token' => 'ewwww',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => "This password reset token is invalid.",
                'status' => 400
            ]);
    }

    public function test_can_resend_verification_email_to_unverified_user()
    {
        // Event::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/email/verification/resend', [
            'email' => $user->email
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification link sent successfully.',
                'data' => [
                    'email' => $user->email
                ]
            ]);

        // Event::assertDispatched(Registered::class);
    }

    public function test_cannot_resend_verification_email_to_verified_user()
    {
        $user = User::factory()->create([
            'email_verified_at' => now()
        ]);

        $response = $this->postJson('/api/email/verification/resend', [
            'email' => $user->email
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Email address is already verified.',
                'status' => 403
            ]);
    }

    public function test_cannot_resend_verification_email_to_nonexistent_user()
    {
        $response = $this->postJson('/api/email/verification/resend', [
            'email' => 'nonexistent@example.com'
        ]);

        $response->assertStatus(404);
    }

    public function test_email_validation_rules_are_enforced()
    {
        $response = $this->postJson('/api/email/verification/resend', [
            'email' => ''
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'status',
                'success',
                'message',
                'data' => ['email']
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
                'status' => 422
            ]);
    }
}
