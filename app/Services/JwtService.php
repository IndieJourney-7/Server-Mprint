<?php

namespace App\Services;

class JwtService
{
    private string $secret;
    private int $ttl;

    public function __construct()
    {
        $this->secret = env('JWT_SECRET', 'default_secret_key');
        $this->ttl = (int) env('JWT_TTL', 30); // minutes
    }

    /**
     * Generate JWT token
     */
    public function generateToken(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]));

        $payload['iat'] = time();
        $payload['exp'] = time() + ($this->ttl * 60);

        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->generateSignature($header, $payloadEncoded);

        return "$header.$payloadEncoded.$signature";
    }

    /**
     * Verify and decode JWT token
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expectedSignature = $this->generateSignature($header, $payload);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decode payload
        $decodedPayload = json_decode($this->base64UrlDecode($payload), true);

        if (!$decodedPayload) {
            return null;
        }

        // Check expiration
        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            return null;
        }

        return $decodedPayload;
    }

    /**
     * Generate HMAC signature
     */
    private function generateSignature(string $header, string $payload): string
    {
        $data = "$header.$payload";
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string
    {
        $padding = 4 - (strlen($data) % 4);
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
