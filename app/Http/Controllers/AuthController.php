<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * @OA\Info(
 *     title="News Aggregator API",
 *     version="1.0.0",
 *     description="A RESTful API for managing news articles and user preferences, built with Laravel 11, Sanctum authentication, Redis caching, and rate limiting (60 requests/minute per IP).",
 * )
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="apiKey",
 *     in="header",
 *     name="Authorization",
 *     description="Enter your Sanctum token in the format: Bearer <token>. Obtain it via POST /api/login."
 * )
 */
class AuthController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe", description="The user's full name"),
     *             @OA\Property(property="email", type="string", example="john@example.com", description="The user's email address"),
     *             @OA\Property(property="password", type="string", example="password123", description="The user's password (min 8 characters)"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User registered successfully. Please verify your email to log in."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="access_token", type="string", example="eKjFIGj3VfXcZXxdKY3iSnj3bxTlxvGLB"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="x_id", type="string", example="jmxVbpG"),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                 )
     *             ),
     *             @OA\Property(property="status", type="integer", example=201)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bad request"),
     *             @OA\Property(property="data", type="null", example=null),
     *             @OA\Property(property="status", type="integer", example=400)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed.(note: Each field returns only one error message at a time, showing possible validation errors for the given fields.)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example={"The email has already been taken.", "The email must be a valid email address."})
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="array", 
     *                     @OA\Items(type="string", example={"The name field is required.", "The name must not exceed 255 characters."})
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example={"The password must be at least 8 characters.", "The password must contain at least one uppercase letter.", "The password must contain at least one number.", "The password must contain at least one special character."}
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="status", type="integer", example=422)
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too Many Requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Too many requests. Please try again later."),
     *             @OA\Property(property="data", type="null", example=null),
     *             @OA\Property(property="status", type="integer", example=429)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred: <error-message>"),
     *             @OA\Property(property="data", type="null", example=null),
     *             @OA\Property(property="status", type="integer", example=500)
     *         )
     *     )
     * )
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create($request->validated());

        $token = $user->createToken('auth_token')->plainTextToken;

        event(new Registered($user));

        return $this->successResponse(
            [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user
            ],
            'User registered successfully. Please verify your email to log in.',
            201
        );
    }

    /** verify the email */

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return view('email-verified', ['verified' => false]);
        }

        if ($user->hasVerifiedEmail()) {
            return view('email-verified', ['verified' => true]);
        }
        $user->markEmailAsVerified();

        return view('email-verified', ['verified' => true]);
    }
    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Log in a user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="email", type="string", example="john@example.com", description="The user's email address"),
     *             @OA\Property(property="password", type="string", example="password123", description="The user's password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="access_token", type="string", example="1|eKjFIGj3VfXcZXxdKY3iSnj3bxTlxvGLB"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_at", type="string", format="datetime", example="2024-02-24T12:00:00Z")
     *             ),
     *             @OA\Property(property="status", type="integer", example=200)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The provided credentials are incorrect."),
     *             @OA\Property(property="status", type="integer", example=401)
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Email not verified",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Please verify your email address before logging in."),
     *             @OA\Property(property="status", type="integer", example=403)
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)
            ->select(['id', 'password', 'email_verified_at'])
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('The provided credentials are incorrect.', 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return $this->errorResponse('Please verify your email address before logging in.', 403);
        }

        $token = $user->createToken('auth_token');
        $plainTextToken = $token->plainTextToken;
        $expiresAt = $token->accessToken->expires_at ?? now()->addMinutes(config('sanctum.expiration', 1440));

        return $this->successResponse(
            [
                'access_token' => $plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toDateTimeString(),
            ],
            'Login successful'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Log out a user",
     *     tags={"Authentication"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logged out successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logged out successfully"),
     *             @OA\Property(property="status", type="integer", example=200)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not authenticated"),
     *             @OA\Property(property="status", type="integer", example=401)
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('User not authenticated', 401);
        }

        // Revoke the current access token
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/password/reset",
     *     summary="Generate a password reset token",
     *     description="This endpoint generates a password reset token for a given email address. The token can be used to reset the user's password.",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com", description="The email address of the user requesting a password reset.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset token generated successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password reset token generated. Use this token to reset your password."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="reset_token", type="string", example="abcdef123456"),
     *                 @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No user found with this email address.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=404),
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No user found with this email address.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=422),
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The email field is required."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string", example="The email must be a valid email address."))
     *             )
     *         )
     *     )
     * )
     */
    public function sendPasswordResetLink(PasswordResetLinkRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->errorResponse('No user found with this email address.', 404);
        }
        $status = Password::sendResetLink($request->only('email'));
        Password::broker()->createToken($user);

        return $this->successResponse(
            ['status' => __($status), 'email' => $request->email],
            'Password reset token generated. Use this token to reset your password.'
        );
    }


    /**
     * Reset the user’s password using the provided token.
     */
    public function resetPassword(ResetPasswordRequest $request)
    {

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                if ($user) {
                    $user->forceFill(['password' => $password])->save();
                    // Optionally revoke all tokens after password reset
                    $user->tokens()->delete();
                }
            }
        );

        return $status === Password::PASSWORD_RESET
            ? $this->successResponse(null, 'Password reset successfully')
            : $this->errorResponse(__($status), 400);
    }
}
