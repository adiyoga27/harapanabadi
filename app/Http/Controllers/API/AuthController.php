<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * API Authentication Controller
 *
 * Handles login, logout, and token management for API access.
 */
class AuthController extends Controller
{
    private $clientId;
    private $clientSecret;

    public function __construct()
    {
        $this->clientId = env('OAUTH_CLIENT_ID', 1);
        $this->clientSecret = env('OAUTH_CLIENT_SECRET', 'wEJ2u9vAycxICEdv6k1PkmGNpOzYW60scrhds22v');
    }

    private function getClientId()
    {
        return $this->clientId ?: 1;
    }

    private function getClientSecret()
    {
        return $this->clientSecret ?: 'wEJ2u9vAycxICEdv6k1PkmGNpOzYW60scrhds22v';
    }

    /**
     * Login to get OAuth2 token.
     *
     * Use your username and password to receive a Bearer token.
     * Default credentials can be configured in .env (API_USERNAME / API_PASSWORD).
     *
     * @bodyParam username string required The username. Example: admin
     * @bodyParam password string required The password. Example: Adiyoga1996
     *
     * @response {
     *   "token_type": "Bearer",
     *   "expires_in": 31536000,
     *   "access_token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
     *   "refresh_token": "def50200..."
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

        $request->request->add([
            'grant_type' => 'password',
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'username' => $request->username,
            'password' => $request->password,
            'scope' => '*',
        ]);

        $tokenRequest = Request::create('/oauth/token', 'POST', $request->all());

        $response = app()->handle($tokenRequest);

        if ($response->status() !== 200) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed.',
            ], 401);
        }

        $data = json_decode($response->getContent(), true);

        return response()->json([
            'success' => true,
            'token_type' => $data['token_type'],
            'expires_in' => $data['expires_in'],
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
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
     * Refresh OAuth2 token.
     *
     * @bodyParam refresh_token string required The refresh token from login.
     *
     * @response {
     *   "token_type": "Bearer",
     *   "expires_in": 31536000,
     *   "access_token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
     *   "refresh_token": "def50200..."
     * }
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $request->request->add([
            'grant_type' => 'refresh_token',
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'refresh_token' => $request->refresh_token,
            'scope' => '*',
        ]);

        $tokenRequest = Request::create('/oauth/token', 'POST', $request->all());

        $response = app()->handle($tokenRequest);

        if ($response->status() !== 200) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed.',
            ], 401);
        }

        $data = json_decode($response->getContent(), true);

        return response()->json([
            'success' => true,
            'token_type' => $data['token_type'],
            'expires_in' => $data['expires_in'],
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
        ]);
    }

    /**
     * Logout (revoke token).
     *
     * @authenticated
     *
     * @response {
     *   "success": true,
     *   "message": "Successfully logged out."
     * }
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * Get authenticated user.
     *
     * @authenticated
     *
     * @response {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "username": "admin",
     *     "email": "admin@example.com",
     *     "full_name": "Admin User"
     *   }
     * }
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
