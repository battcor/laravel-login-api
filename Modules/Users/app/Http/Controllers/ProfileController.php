<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Identity\Notifications\VerifyEmailNotification;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email', 'email_verified_at']),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
        ]);

        $emailChanged = isset($validated['email']) && $validated['email'] !== $user->email;
        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->notify(new VerifyEmailNotification);
        }

        return response()->json([
            'message' => 'Profile updated.',
            'user' => $user->only(['id', 'name', 'email', 'email_verified_at']),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted.']);
    }
}
