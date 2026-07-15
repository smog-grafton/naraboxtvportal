<?php

namespace App\Http\Controllers\Api;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Models\EmailVerificationCode;
use App\Models\PasswordReset;
use App\Models\PhoneVerificationCode;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\WebBridgeToken;
use App\Services\EmailService;
use App\Services\IoTeCService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * @group Authentication
 *
 * Register, login, profile, OAuth, password reset, and email verification.
 */
class AuthController extends Controller
{
    protected function normalizeBridgeNextPath(?string $candidate): string
    {
        $value = trim((string) ($candidate ?? ''));
        if ($value === '') {
            return '/subscriptions';
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            try {
                $parts = parse_url($value);
                $path = $parts['path'] ?? '/subscriptions';
                $query = isset($parts['query']) ? ('?'.$parts['query']) : '';
                $fragment = isset($parts['fragment']) ? ('#'.$parts['fragment']) : '';
                $value = $path.$query.$fragment;
            } catch (\Throwable) {
                return '/subscriptions';
            }
        }

        if (! str_starts_with($value, '/')) {
            $value = '/'.$value;
        }

        // Prevent path traversal / protocol-like values in "next".
        if (str_contains($value, '..') || str_contains($value, '\\') || str_starts_with($value, '//')) {
            return '/subscriptions';
        }

        return Str::limit($value, 500, '');
    }

    /**
     * Build a consistent authentication response payload.
     */
    protected function buildAuthPayload(User $user, string $token, string $provider, bool $isNewUser = false, bool $requiresVerification = false): array
    {
        return [
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
                'role' => $user->role ? $user->role->name : 'customer',
                'is_creator' => $user->isCreator(),
                'creator_application' => $this->formatCreatorApplication($user),
            ],
            'token' => $token,
            'auth_provider' => $provider,
            'is_new_user' => $isNewUser,
            'requires_verification' => $requiresVerification,
        ];
    }

    /**
     * When true, registration queues email verification and phone auth uses SMS OTP.
     * When false (MVP default), users get a token immediately without codes.
     */
    protected function verificationCodesRequired(): bool
    {
        return (bool) config('api.require_verification_codes', false);
    }

    /**
     * Register a new user
     *
     * With {@see verificationCodesRequired()} false (MVP): creates a verified account and returns a token.
     * When true: creates an unverified account and emails a 6-digit code (no token until {@see verifyEmail}).
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:255',
        ]);

        $validator->after(function ($v) use ($request) {
            $email = trim((string) $request->input('email', ''));
            $phone = trim((string) $request->input('phone', ''));
            if ($email === '' && $phone === '') {
                $v->errors()->add('email', 'Email or phone is required.');
                $v->errors()->add('phone', 'Email or phone is required.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $emailIn = trim((string) $request->input('email', ''));
        $phoneRaw = trim((string) $request->input('phone', ''));
        $phoneNorm = $phoneRaw !== '' ? IoTeCService::normalizePhone($phoneRaw) : null;

        if ($emailIn !== '') {
            $emailNorm = strtolower($emailIn);
            if (User::whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm])->exists()) {
                return response()->json([
                    'error' => 'Email already registered'
                ], 422);
            }
        }

        if ($phoneNorm !== null && User::where('phone', $phoneNorm)->exists()) {
            return response()->json([
                'error' => 'Phone number already registered',
                'messages' => ['phone' => ['This phone number is already in use.']],
            ], 422);
        }

        if ($phoneNorm !== null && $phoneRaw !== '' && $phoneRaw !== $phoneNorm) {
            if (User::where('phone', $phoneRaw)->exists()) {
                return response()->json([
                    'error' => 'Phone number already registered',
                    'messages' => ['phone' => ['This phone number is already in use.']],
                ], 422);
            }
        }

        $customerRole = Role::where('name', 'customer')->first();
        if (!$customerRole) {
            return response()->json([
                'error' => 'Customer role not found'
            ], 500);
        }

        $emailToStore = $emailIn !== '' ? $emailIn : null;
        if ($emailToStore === null) {
            $digits = preg_replace('/\D/', '', $phoneNorm ?? $phoneRaw) ?: 'x';
            $emailToStore = 'phone_'.$digits.'@phone-auth.local';
            $suffix = 0;
            while (User::where('email', $emailToStore)->exists()) {
                $suffix++;
                $emailToStore = 'phone_'.$digits.'_'.$suffix.'@phone-auth.local';
            }
        }

        $phoneOnlyRegister = $emailIn === '';

        $user = User::create([
            'name' => $request->name,
            'email' => $emailToStore,
            'password' => Hash::make($request->password),
            'phone' => $phoneNorm ?? ($phoneRaw !== '' ? $phoneRaw : null),
            'role_id' => $customerRole->id,
            'plan' => 'FREE',
            'plan_status' => 'NONE',
            'email_verified_at' => ($this->verificationCodesRequired() && ! $phoneOnlyRegister) ? null : now(),
        ]);

        if (! $this->verificationCodesRequired() || $phoneOnlyRegister) {
            $token = $user->createToken('auth_token')->plainTextToken;

            event(new UserRegistered($user));

            return response()->json([
                'message' => 'Registration successful',
                'data' => $this->buildAuthPayload($user, $token, 'email', true, false),
            ], 201);
        }

        $verificationCode = EmailVerificationCode::createForEmail($user->email);
        $emailSent = EmailService::sendVerificationCode($user->email, $verificationCode->code);

        if (! $emailSent) {
            $user->delete();

            return response()->json([
                'error' => 'Failed to send verification email. Check mail configuration.',
            ], 500);
        }

        return response()->json([
            'message' => 'We sent a 6-digit code to your email. Enter it to activate your account.',
            'data' => [
                'email' => $user->email,
                'requires_verification' => true,
            ],
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

        event(new UserRegistered($user));

        // Generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully',
            'data' => $this->buildAuthPayload($user, $token, 'email', false, false),
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
     * Login user with email and password
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:255',
            'password' => 'required|string',
        ]);

        $validator->after(function ($v) use ($request) {
            $email = trim((string) $request->input('email', ''));
            $phone = trim((string) $request->input('phone', ''));
            if ($email === '' && $phone === '') {
                $v->errors()->add('email', 'Email or phone is required.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = null;
        $email = trim((string) $request->input('email', ''));
        $phoneRaw = trim((string) $request->input('phone', ''));

        if ($email !== '') {
            $emailNorm = strtolower($email);
            $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm])->first();
        } elseif ($phoneRaw !== '') {
            $normalized = IoTeCService::normalizePhone($phoneRaw);
            $user = User::where('phone', $normalized)->first();
            if (! $user) {
                $user = User::where('phone', $phoneRaw)->first();
            }
        }

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
            'data' => $this->buildAuthPayload(
                $user,
                $token,
                'email',
                false,
                $this->verificationCodesRequired() && ! $user->email_verified_at,
            ),
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
                'role' => $user->role ? $user->role->name : 'customer',
                'is_creator' => $user->isCreator(),
                'creator_application' => $this->formatCreatorApplication($user),
            ]
        ]);
    }

    private function formatCreatorApplication(\App\Models\User $user): ?array
    {
        $application = $user->creatorApplication;
        if (!$application) {
            return null;
        }

        return [
            'id'               => $application->id,
            'creator_type'     => $application->creator_type,
            'display_name'     => $application->display_name,
            'status'           => $application->status,
            'rejection_reason' => $application->rejection_reason,
            'submitted_at'     => $application->created_at?->toIso8601String(),
            'reviewed_at'      => $application->reviewed_at?->toIso8601String(),
        ];
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
     * Redirect to Google OAuth (web flow)
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google OAuth callback (web flow)
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Reuse shared handler for Google auth
            [$user, $isNewUser] = $this->findOrCreateUserFromGoogleUser($googleUser);

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
     * Get Google OAuth URL (for frontend web)
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

    /**
     * Handle mobile Google login/register using an access token.
     */
    public function googleMobile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->input('access_token'));

            [$user, $isNewUser] = $this->findOrCreateUserFromGoogleUser($googleUser);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful via Google',
                'data' => $this->buildAuthPayload($user, $token, 'google', $isNewUser, false),
            ]);
        } catch (\Exception $e) {
            \Log::error('Google mobile OAuth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Failed to authenticate with Google',
            ], 500);
        }
    }

    /**
     * Request phone login OTP (skipped when verification codes are disabled — MVP).
     */
    public function requestPhoneOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        if (! $this->verificationCodesRequired()) {
            return response()->json([
                'message' => 'OK',
            ]);
        }

        $phone = $request->input('phone');

        $otp = PhoneVerificationCode::createForPhone($phone);

        $sent = SmsService::sendOtp($phone, $otp->code);

        if (! $sent) {
            return response()->json([
                'error' => 'Failed to send OTP',
            ], 500);
        }

        return response()->json([
            'message' => 'OTP sent successfully',
        ]);
    }

    /**
     * Resolve user for phone sign-in: match by phone or email, or create with a placeholder email if needed.
     *
     * @return array{0: User, 1: bool}
     */
    protected function findOrCreateUserForPhoneAuth(string $phone, Request $request): array
    {
        $user = User::where('phone', $phone)->first();
        if ($user) {
            return [$user, false];
        }

        if ($request->filled('email')) {
            $user = User::where('email', $request->input('email'))->first();
            if ($user) {
                if (! $user->phone) {
                    $user->update(['phone' => $phone]);
                }

                return [$user, false];
            }
        }

        $customerRole = Role::where('name', 'customer')->first();
        if (! $customerRole) {
            throw new \RuntimeException('Customer role not found');
        }

        $email = $request->input('email');
        if (! $email) {
            $digits = preg_replace('/\D/', '', $phone) ?: 'x';
            $email = 'phone_'.$digits.'@phone-auth.local';
            $suffix = 0;
            while (User::where('email', $email)->exists()) {
                $suffix++;
                $email = 'phone_'.$digits.'_'.$suffix.'@phone-auth.local';
            }
        }

        $name = $request->input('name');
        if (! $name) {
            $digits = preg_replace('/\D/', '', $phone);
            $tail = strlen($digits) >= 4 ? substr($digits, -4) : ($digits ?: 'user');
            $name = 'User '.$tail;
        }

        $mvp = ! $this->verificationCodesRequired();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(uniqid('phone_', true)),
            'phone' => $phone,
            'role_id' => $customerRole->id,
            'plan' => 'FREE',
            'plan_status' => 'NONE',
            'email_verified_at' => $mvp ? now() : ($request->filled('email') ? now() : null),
        ]);

        return [$user, true];
    }

    /**
     * Verify phone OTP and login/register user (OTP optional when verification codes are disabled — MVP).
     */
    public function verifyPhoneOtp(Request $request)
    {
        $requireOtp = $this->verificationCodesRequired();

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:255',
            'code' => $requireOtp ? 'required|string|size:6' : 'nullable|string|max:6',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $phone = $request->input('phone');

        if ($requireOtp) {
            $code = $request->input('code');
            $record = PhoneVerificationCode::where('phone', $phone)
                ->where('code', $code)
                ->where('used', false)
                ->latest()
                ->first();

            if (! $record || ! $record->isValid()) {
                if ($record) {
                    $record->increment('attempts');
                }

                return response()->json([
                    'error' => 'Invalid or expired OTP code',
                ], 422);
            }

            $record->update(['used' => true]);
        }

        try {
            [$user, $isNewUser] = $this->findOrCreateUserForPhoneAuth($phone, $request);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }

        if ($isNewUser && $user->email && ! str_ends_with(strtolower($user->email), '@phone-auth.local')) {
            try {
                EmailService::sendWelcome($user->email, $user->name);
            } catch (\Throwable $e) {
                \Log::warning('Welcome email failed after phone register: '.$e->getMessage());
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful via phone',
            'data' => $this->buildAuthPayload($user, $token, 'phone', $isNewUser, false),
        ]);
    }

    /**
     * Handle Apple login/register (mobile).
     *
     * NOTE: This implementation assumes the mobile app has already obtained and
     * validated the Apple identity token. For production-hardening, wire this
     * to proper server-side Apple token validation.
     */
    public function appleMobile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'apple_user_id' => 'required|string',
            'email' => 'sometimes|nullable|email|max:255',
            'name' => 'sometimes|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $appleUserId = $request->input('apple_user_id');
        $email = $request->input('email');

        // Try to find existing social account first
        $social = SocialAccount::where('provider', 'apple')
            ->where('provider_user_id', $appleUserId)
            ->first();

        $isNewUser = false;
        $user = $social?->user;

        if (!$user && $email) {
            $user = User::where('email', $email)->first();
        }

        if (!$user) {
            $customerRole = Role::where('name', 'customer')->first();
            if (!$customerRole) {
                return response()->json([
                    'error' => 'Customer role not found',
                ], 500);
            }

            $user = User::create([
                'name' => $request->input('name') ?: 'Apple User',
                'email' => $email,
                'password' => Hash::make(uniqid('apple_', true)),
                'role_id' => $customerRole->id,
                'plan' => 'FREE',
                'plan_status' => 'NONE',
                'email_verified_at' => $email ? now() : null,
            ]);

            $isNewUser = true;

            if ($user->email) {
                EmailService::sendWelcome($user->email, $user->name);
            }
        }

        // Ensure social account exists/updated
        SocialAccount::updateOrCreate(
            [
                'provider' => 'apple',
                'provider_user_id' => $appleUserId,
            ],
            [
                'user_id' => $user->id,
                'email' => $email,
                'last_login_at' => now(),
            ]
        );

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful via Apple',
            'data' => $this->buildAuthPayload($user, $token, 'apple', $isNewUser, false),
        ]);
    }

    /**
     * Shared helper: find or create a user from a Google Socialite user.
     *
     * @return array{0: User, 1: bool} [user, isNewUser]
     */
    protected function findOrCreateUserFromGoogleUser($googleUser): array
    {
        $isNewUser = false;

        $email = $googleUser->getEmail();
        $googleId = $googleUser->getId();

        $social = null;
        if ($googleId) {
            $social = SocialAccount::where('provider', 'google')
                ->where('provider_user_id', $googleId)
                ->first();
        }

        $user = $social?->user;

        if (!$user && $email) {
            $user = User::where('email', $email)->first();
        }

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
            $customerRole = Role::where('name', 'customer')->first();
            if (!$customerRole) {
                abort(500, 'Customer role not found');
            }

            $user = User::create([
                'name' => $googleUser->getName() ?: ($email ?: 'Google User'),
                'email' => $email,
                'password' => Hash::make(uniqid('google_', true)),
                'avatar' => $googleUser->getAvatar(),
                'role_id' => $customerRole->id,
                'plan' => 'FREE',
                'plan_status' => 'NONE',
                'email_verified_at' => $email ? now() : null,
            ]);

            $isNewUser = true;

            if ($user->email) {
                EmailService::sendWelcome($user->email, $user->name);
            }
        }

        if ($googleId) {
            SocialAccount::updateOrCreate(
                [
                    'provider' => 'google',
                    'provider_user_id' => $googleId,
                ],
                [
                    'user_id' => $user->id,
                    'email' => $email,
                    'raw_profile' => $googleUser->user ?? null,
                    'last_login_at' => now(),
                ]
            );
        }

        return [$user, $isNewUser];
    }

    /**
     * Issue one-time bridge token so browser can bootstrap authenticated web session.
     */
    public function issueWebBridgeToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'next_path' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $nextPath = $this->normalizeBridgeNextPath($request->input('next_path'));
        $plainToken = Str::random(80);
        $tokenHash = hash('sha256', $plainToken);
        $ttlMinutes = max((int) config('api.web_bridge_ttl_minutes', 2), 1);

        WebBridgeToken::create([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'next_path' => $nextPath,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'issued_ip' => $request->ip(),
            'issued_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        return response()->json([
            'message' => 'Web bridge token issued',
            'data' => [
                'bridge_token' => $plainToken,
                'next_path' => $nextPath,
                'expires_at' => now()->addMinutes($ttlMinutes)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Consume one-time bridge token and return auth payload for web app.
     */
    public function consumeWebBridgeToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bridge_token' => 'required|string|min:40|max:255',
            'next_path' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $tokenHash = hash('sha256', (string) $request->input('bridge_token'));
        $bridge = WebBridgeToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $bridge) {
            return response()->json([
                'error' => 'Bridge token is invalid or expired.',
            ], 422);
        }

        $user = User::find($bridge->user_id);
        if (! $user) {
            $bridge->update([
                'used_at' => now(),
                'consumed_ip' => $request->ip(),
                'consumed_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            ]);

            return response()->json([
                'error' => 'Account not found for this token.',
            ], 404);
        }

        $bridge->update([
            'used_at' => now(),
            'consumed_ip' => $request->ip(),
            'consumed_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        $token = $user->createToken('web_bridge_token')->plainTextToken;
        $nextPath = $this->normalizeBridgeNextPath($request->input('next_path') ?: $bridge->next_path);

        return response()->json([
            'message' => 'Bridge token consumed',
            'data' => array_merge(
                $this->buildAuthPayload($user, $token, 'web_bridge', false, false),
                ['next_path' => $nextPath]
            ),
        ]);
    }
}
