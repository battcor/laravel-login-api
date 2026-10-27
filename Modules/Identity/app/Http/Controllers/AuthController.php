<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Identity\Notifications\PasswordChangedNotification;
use Modules\Identity\Notifications\VerifyEmailNotification;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($validated);

        $user->notify(new VerifyEmailNotification);

        return response()->json([
            'message' => 'Registration successful. Check your email to verify your account.',
            'user' => $this->userData($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Verify your email address before logging in.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $this->userData($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function verifyEmail(string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json(['message' => 'Email address verified.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email address is already verified.']);
        }

        $user->notify(new VerifyEmailNotification);

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($validated);

        return response()->json([
            'message' => 'If an account exists for that email, a password reset email has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($validated, function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();
            $user->notify(new PasswordChangedNotification);
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password has been reset.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $validated['password']])->save();
        $currentToken = $user->currentAccessToken();
        $user->tokens()
            ->when(
                $currentToken instanceof PersonalAccessToken,
                fn ($tokens) => $tokens->whereKeyNot($currentToken->getKey()),
            )
            ->delete();
        $user->notify(new PasswordChangedNotification);

        return response()->json(['message' => 'Password has been changed.']);
    }

    private function userData(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'email_verified_at']);
    }
}
