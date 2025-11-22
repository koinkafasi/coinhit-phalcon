<?php

namespace Tahmin\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tahmin\Models\User;

class JwtService
{
    private $config;

    public function __construct()
    {
        $di = \Phalcon\Di\Di::getDefault();
        $this->config = $di->get('config')->jwt;
    }

    /**
     * Generate access token for user
     */
    public function generateAccessToken(User $user): string
    {
        $payload = [
            'iss' => $this->config->issuer,
            'sub' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'iat' => time(),
            'exp' => time() + $this->config->access_token_expire,
        ];

        return JWT::encode($payload, $this->config->secret, $this->config->algorithm);
    }

    /**
     * Generate refresh token for user
     */
    public function generateRefreshToken(User $user): string
    {
        $payload = [
            'iss' => $this->config->issuer,
            'sub' => $user->id,
            'type' => 'refresh',
            'iat' => time(),
            'exp' => time() + $this->config->refresh_token_expire,
        ];

        return JWT::encode($payload, $this->config->secret, $this->config->algorithm);
    }

    /**
     * Verify and decode JWT token
     */
    public function verifyToken(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->config->secret, $this->config->algorithm));
            return $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get user from token
     */
    public function getUserFromToken(string $token): ?User
    {
        $decoded = $this->verifyToken($token);
        if (!$decoded) {
            return null;
        }

        return User::findFirst($decoded->sub);
    }

    /**
     * Extract token from Authorization header
     */
    public function extractTokenFromHeader(string $authHeader): ?string
    {
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
