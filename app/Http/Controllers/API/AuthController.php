<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Login to get Sanctum API token.
     *
     * @bodyParam username string required The username.
     * @bodyParam password string required The password.
     *
     * @response {
     *   "success": true,
     *   "token_type": "Bearer",
     *   "expires_in": 1296000,
     *   "access_token": "1|...",
     *   "user": { ... }
     * }
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (!$user->business || !$user->business->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business is inactive.',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'User account is inactive.',
            ], 403);
        }

        if (!$user->allow_login) {
            return response()->json([
                'success' => false,
                'message' => 'Login not allowed for this user.',
            ], 403);
        }

        $expiresAt = now()->addDays(15);
        $token = $user->createToken('login', ['*'], $expiresAt);

        return response()->json([
            'success' => true,
            'token_type' => 'Bearer',
            'expires_in' => $expiresAt->diffInSeconds(now()),
            'access_token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'full_name' => $user->user_full_name,
                'business_id' => $user->business_id,
            ],
        ]);
    }

    /**
     * Refresh token.
     *
     * Sends current Bearer token, receives new one.
     *
     * @bodyParam access_token string The current access token.
     */
    public function refresh(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            $token = $request->input('access_token');
        }

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided.',
            ], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token.',
            ], 401);
        }

        $user = $accessToken->tokenable;
        $accessToken->delete();

        $expiresAt = now()->addDays(15);
        $newToken = $user->createToken('login', ['*'], $expiresAt);

        return response()->json([
            'success' => true,
            'token_type' => 'Bearer',
            'expires_in' => $expiresAt->diffInSeconds(now()),
            'access_token' => $newToken->plainTextToken,
        ]);
    }

    /**
     * Logout (revoke token).
     *
     * @authenticated
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * Get authenticated user.
     *
     * @authenticated
     */
    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'full_name' => $user->user_full_name,
                'business_id' => $user->business_id,
                'status' => $user->status,
            ],
        ]);
    }
}
