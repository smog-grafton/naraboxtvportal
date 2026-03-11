<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\VerificationCodeMail;
use App\Mail\RegistrationCompleteMail;
use App\Mail\PasswordResetMail;
use App\Services\EmailService;
use App\Models\PasswordReset;
use App\Models\User;
use App\Models\Role;
use App\Models\EmailVerificationCode;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        // Check if user already exists
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            return response()->json([
                'error' => 'Email already registered'
            ], 422);
        }

        // Get customer role
        $customerRole = Role::where('name', 'customer')->first();
        if (!$customerRole) {
            return response()->json([
                'error' => 'Customer role not found'
            ], 500);
        }

        // Create user (auto-verified - email verification disabled for now)
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role_id' => $customerRole->id,
            'plan' => 'FREE',
            'plan_status' => 'NONE',
            'email_verified_at' => now(), // Auto-verify users on registration
        ]);

        // Send welcome email
        EmailService::sendWelcome($user->email, $user->name);

        // Generate token for immediate access
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful. Welcome to NaraBox TV!',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'plan' => $user->plan,
                    'planStatus' => $user->plan_status,
                    'renewalDate' => $user->renewal_date?->format('Y-m-d'),
                    'emailVerified' => true,
                ],
                'token' => $token,
                'requires_verification' => false,
            ]
        ], 201);
    }

    /**
     * Verify email with code
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $verificationCode = EmailVerificationCode::where('email', $request->email)
            ->where('code', $request->code)
            ->where('used', false)
            ->first();

        if (!$verificationCode || !$verificationCode->isValid()) {
            return response()->json([
                'error' => 'Invalid or expired verification code'
            ], 422);
        }

        // Mark code as used
        $verificationCode->update(['used' => true]);

        // Verify user
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        $user->update([
            'email_verified_at' => now(),
        ]);

        // Send welcome email
        EmailService::sendWelcome($user->email, $user->name);

        // Generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'plan' => $user->plan,
                    'planStatus' => $user->plan_status,
                    'renewalDate' => $user->renewal_date?->format('Y-m-d'),
                    'emailVerified' => true,
                ],
                'token' => $token,
            ]
        ]);
    }

    /**
     * Resend verification code
     */
    public function resendVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'error' => 'Email already verified'
            ], 422);
        }

        // Generate new code
        $verificationCode = EmailVerificationCode::createForEmail($user->email);
        
        $emailSent = EmailService::sendVerificationCode($user->email, $verificationCode->code);
        
        if (!$emailSent) {
            return response()->json([
                'error' => 'Failed to send verification email'
            ], 500);
        }

        return response()->json([
            'message' => 'Verification code resent successfully'
        ]);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid credentials'
            ], 401);
        }

        // Allow login even if email is not verified, but mark it
        // Frontend will handle showing verification prompt

        // Generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'plan' => $user->plan,
                    'planStatus' => $user->plan_status,
                    'renewalDate' => $user->renewal_date?->format('Y-m-d'),
                    'emailVerified' => (bool) $user->email_verified_at,
                ],
                'token' => $token,
                'requires_verification' => !$user->email_verified_at,
            ]
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 401);
        }

        // Check for expired subscriptions and update them
        \App\Models\UserSubscription::where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'EXPIRED']);
        
        // Get active subscription
        $activeSubscription = \App\Models\UserSubscription::where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->with('subscriptionPlan')
            ->latest()
            ->first();
        
        // Update user's plan_status if no active subscription but user table says ACTIVE
        if (!$activeSubscription && $user->plan_status === 'ACTIVE') {
            $user->update([
                'plan_status' => 'NONE',
                'plan' => 'FREE',
                'renewal_date' => null,
            ]);
            $user->refresh();
        }
            
        // Check for pending subscription payment
        $pendingSubscription = \App\Models\PaymentTransaction::where('user_id', $user->id)
            ->where('type', 'SUBSCRIPTION')
            ->where('status', 'PENDING')
            ->with('subscriptionPlan')
            ->latest()
            ->first();

        // Determine plan display name and status
        $planDisplayName = $user->plan; // Default to ENUM value
        $planStatus = 'NONE'; // Default to NONE if no active subscription
        $renewalDate = null;
        
        if ($pendingSubscription) {
            $planDisplayName = $pendingSubscription->subscriptionPlan->name ?? $user->plan;
            $planStatus = 'PENDING';
        } elseif ($activeSubscription) {
            $planDisplayName = $activeSubscription->subscriptionPlan->name ?? $user->plan;
            $planStatus = 'ACTIVE';
            $renewalDate = $activeSubscription->expires_at->format('Y-m-d');
        } else {
            // No active subscription - check if user has expired subscriptions
            $expiredSubscription = UserSubscription::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '<=', now())
                ->latest()
                ->first();
            
            if ($expiredSubscription) {
                // Update expired subscription status
                $expiredSubscription->update(['status' => 'EXPIRED']);
                $planStatus = 'EXPIRED';
            } else {
                $planStatus = 'NONE';
            }
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'plan' => $planDisplayName,
                'planStatus' => $planStatus,
                'renewalDate' => $renewalDate,
                'emailVerified' => (bool) $user->email_verified_at,
                'pendingSubscription' => $pendingSubscription ? [
                    'plan' => $pendingSubscription->subscriptionPlan->name ?? 'Unknown',
                    'status' => 'PENDING',
                    'transaction_ref' => $pendingSubscription->transaction_ref,
                ] : null,
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user->update($request->only(['name', 'email', 'phone']));

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'plan' => $user->plan,
                'planStatus' => $user->plan_status,
                'renewalDate' => $user->renewal_date?->format('Y-m-d'),
                'emailVerified' => (bool) $user->email_verified_at,
            ]
        ]);
    }

    /**
     * Delete user account
     */
    public function deleteAccount(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 401);
        }

        // Delete all user tokens
        $user->tokens()->delete();
        
        // Delete user account
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully'
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            // Check if user exists
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Update avatar if available
                if ($googleUser->getAvatar() && !$user->avatar) {
                    $user->update(['avatar' => $googleUser->getAvatar()]);
                }
                // Verify email for Google OAuth users (Google emails are pre-verified)
                if (!$user->email_verified_at) {
                    $user->update(['email_verified_at' => now()]);
                }
            } else {
                // Get customer role
                $customerRole = Role::where('name', 'customer')->first();
                if (!$customerRole) {
                    return response()->json([
                        'error' => 'Customer role not found'
                    ], 500);
                }

                // Create new user
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'password' => Hash::make(uniqid()), // Random password for OAuth users
                    'avatar' => $googleUser->getAvatar(),
                    'role_id' => $customerRole->id,
                    'plan' => 'FREE',
                    'plan_status' => 'NONE',
                    'email_verified_at' => now(), // Google emails are pre-verified
                ]);

                // Send welcome email
                EmailService::sendWelcome($user->email, $user->name);
            }

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Redirect to frontend with token
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect("{$frontendUrl}/auth/callback?token={$token}&user=" . urlencode(json_encode([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])));

        } catch (\Exception $e) {
            \Log::error('Google OAuth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect("{$frontendUrl}/auth/callback?error=" . urlencode($e->getMessage()));
        }
    }

    /**
     * Get Google OAuth URL (for frontend)
     */
    public function getGoogleAuthUrl()
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        return response()->json([
            'data' => [
                'url' => $url
            ]
        ]);
    }

    /**
     * Request password reset
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            // Don't reveal if user exists or not for security
            return response()->json([
                'message' => 'If an account exists with this email, a password reset link has been sent.'
            ]);
        }

        // Create password reset token
        $passwordReset = PasswordReset::createForEmail($user->email);

        // Send password reset email using EmailService
        $emailSent = EmailService::send($user->email, 'password_reset', [
            'email' => $user->email,
            'token' => $passwordReset->token,
            'reset_url' => (config('app.frontend_url') ?? env('FRONTEND_URL', 'http://localhost:3000')) . '/reset-password?token=' . $passwordReset->token . '&email=' . urlencode($user->email),
        ]);
        
        if (!$emailSent) {
            return response()->json([
                'error' => 'Failed to send password reset email'
            ], 500);
        }

        return response()->json([
            'message' => 'If an account exists with this email, a password reset link has been sent.'
        ]);
    }

    /**
     * Reset password with token
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $passwordReset = PasswordReset::findByToken($request->token);

        if (!$passwordReset || !$passwordReset->isValid() || $passwordReset->email !== $request->email) {
            return response()->json([
                'error' => 'Invalid or expired reset token'
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Mark token as used
        $passwordReset->update(['used' => true]);

        // Invalidate all existing tokens for this user
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successful. Please login with your new password.'
        ]);
    }
}
