<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent to your email.',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password has been reset successfully.',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Redirect to OAuth provider.
     */
    public function redirectToProvider(string $provider)
    {
        // Validate provider name
        $validProviders = ['google', 'github'];
        if (!in_array($provider, $validProviders)) {
            return response()->json([
                'message' => 'Invalid provider',
                'valid_providers' => $validProviders,
            ], 400);
        }

        // Check if provider is enabled in settings
        $isActive = \App\Models\Setting::where('key', "{$provider}_active")
            ->where('group', 'authentication')
            ->value('value');

        if (!$isActive || $isActive === '0') {
            return response()->json([
                'message' => ucfirst($provider) . ' login is not enabled',
                'error' => 'provider_disabled',
            ], 403);
        }

        // Redirect to provider
        return \Laravel\Socialite\Facades\Socialite::driver($provider)->redirect();
    }

    /**
     * Handle OAuth provider callback.
     */
    public function handleProviderCallback(string $provider)
    {
        // Validate provider
        $validProviders = ['google', 'github'];
        if (!in_array($provider, $validProviders)) {
            return response()->json(['message' => 'Invalid provider'], 400);
        }

        // Check if provider is enabled
        $isActive = \App\Models\Setting::where('key', "{$provider}_active")
            ->where('group', 'authentication')
            ->value('value');

        if (!$isActive || $isActive === '0') {
            return response()->json([
                'message' => ucfirst($provider) . ' login is not enabled',
            ], 403);
        }

        try {
            $socialUser = \Laravel\Socialite\Facades\Socialite::driver($provider)->user();

            // Find or create user
            $user = User::where('email', $socialUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'email' => $socialUser->getEmail(),
                    'password' => Hash::make(\Illuminate\Support\Str::random(24)),
                    'email_verified_at' => now(),
                ]);
            }

            // Update provider info
            $user->update([
                "{$provider}_id" => $socialUser->getId(),
            ]);

            // Create token
            $token = $user->createToken('social_auth_token')->plainTextToken;

            // Return JSON or redirect based on Accept header
            if (request()->wantsJson()) {
                return response()->json([
                    'user' => $user,
                    'token' => $token,
                ]);
            }

            // Redirect to frontend with token (adjust URL as needed)
            return redirect()->to(
                config('app.frontend_url', '/') . '?token=' . $token
            );

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Authentication failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available OAuth providers.
     */
    public function getProviders()
    {
        $google = \App\Models\Setting::where('key', 'google_active')
            ->where('group', 'authentication')
            ->value('value');

        $github = \App\Models\Setting::where('key', 'github_active')
            ->where('group', 'authentication')
            ->value('value');

        return response()->json([
            'providers' => [
                'google' => (bool) $google && $google !== '0',
                'github' => (bool) $github && $github !== '0',
            ],
        ]);
    }
}
