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

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Register a new user and return an access token.
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
     * Log in a user and return an access token.
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)
            ->select(['id', 'password'])
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('The provided credentials are incorrect.', 401);
        }

        // if (!$user->hasVerifiedEmail()) {
        //     return $this->errorResponse('Please verify your email address before logging in.', 403);
        // }

        $token = $user->createToken('auth_token');
        $plainTextToken = $token->plainTextToken;
        $expiresAt = $token->accessToken->expires_at ?? now()->addMinutes(config('sanctum.expiration', 1440));

        return $this->successResponse(
            [
                'user' => $user,
                'access_token' => $plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toDateTimeString(),
            ],
            'Login successful'
        );
    }

    /**
     * Log out the authenticated user by revoking the current token.
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
     * Send a password reset link to the user’s email.
     */
    public function sendPasswordResetLink(PasswordResetLinkRequest $request)
    {

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->errorResponse('No user found with this email address.', 404);
        }

        // Generate a password reset token
        $token = Password::broker()->createToken($user);

        return $this->successResponse(
            ['reset_token' => $token, 'email' => $request->email],
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
