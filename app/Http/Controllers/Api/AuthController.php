<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JwtService;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Register (Signup)
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    /**
     * Login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get Authenticated User Profile
     */
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    /**
     * Admin Login with JWT
     */
    public function adminLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is admin
        if (!$user->isAdmin()) {
            throw ValidationException::withMessages([
                'email' => ['You do not have admin access.'],
            ]);
        }

        // Generate JWT token with admin claims
        $token = $this->jwtService->generateToken([
            'adminId' => $user->id,
            'email' => $user->email,
            'role' => 'admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin login successful',
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) env('JWT_TTL', 30) * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
            ],
        ]);
    }

    /**
     * Verify Admin Token (protected by AdminJwt middleware)
     */
    public function verifyAdmin(Request $request)
    {
        // If request reaches here, token is valid (middleware passed)
        return response()->json([
            'success' => true,
            'admin' => [
                'id' => $request->admin_id,
                'email' => $request->admin_email,
                'role' => $request->admin_role,
            ],
        ]);
    }

    /**
     * Admin Logout (stateless - client discards token)
     */
    public function adminLogout(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Admin logged out successfully',
        ]);
    }
}
