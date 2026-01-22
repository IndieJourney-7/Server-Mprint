<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminJwt
{
    private JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided'
            ], 401);
        }

        $payload = $this->jwtService->verifyToken($token);

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        // Verify admin role
        if (!isset($payload['role']) || $payload['role'] !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Admin access required'
            ], 403);
        }

        // Attach admin info to request
        $request->merge([
            'admin_id' => $payload['adminId'] ?? null,
            'admin_email' => $payload['email'] ?? null,
            'admin_role' => $payload['role'] ?? null,
        ]);

        return $next($request);
    }

    /**
     * Extract token from Authorization header
     */
    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }
}
