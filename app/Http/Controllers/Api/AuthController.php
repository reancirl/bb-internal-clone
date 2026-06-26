<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeCard;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Issue a personal access token for valid credentials.
     *
     * The mobile client stores the returned plain-text token and sends it as
     * `Authorization: Bearer {token}` on every subsequent request.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $deviceName = $data['device_name'] ?? 'mobile';
        $token = $user->createToken($deviceName, ['mobile'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'open_time_card' => $this->openTimeCard($user),
        ]);
    }

    /**
     * Return the authenticated user plus their currently-open time card.
     * Mirrors the Inertia shared props (`auth.user` + `openTimeCard`).
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
            'open_time_card' => $this->openTimeCard($user),
        ]);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function openTimeCard(User $user): ?array
    {
        $card = TimeCard::query()
            ->where('user_id', $user->id)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();

        if (! $card) {
            return null;
        }

        return [
            'id' => $card->id,
            'clock_in_at' => $card->clock_in_at?->toIso8601String(),
            'notes' => $card->notes,
        ];
    }
}
