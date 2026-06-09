<?php
// ============================================================
// EGGLAND BD - Auth Middleware
// ============================================================

require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

class Auth {

    public static function require(array $allowedRoles = []): array {
        $user = JWT::fromRequest();

        if (!$user) {
            Response::unauthorized('Authentication required. Please login.');
        }

        if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles)) {
            Response::forbidden('You do not have permission to access this resource.');
        }

        return $user;
    }

    public static function optional(): ?array {
        return JWT::fromRequest();
    }
}

// Alias functions for cleaner usage
function requireAuth(array $roles = []): array {
    return Auth::require($roles);
}

function requireAdmin(): array {
    return Auth::require(['admin']);
}

function requireAgent(): array {
    return Auth::require(['admin', 'agent']);
}

function requireSR(): array {
    return Auth::require(['admin', 'agent', 'sr']);
}

function requireDSR(): array {
    return Auth::require(['admin', 'agent', 'dsr']);
}

function requireAny(): array {
    return Auth::require(['admin', 'agent', 'sr', 'dsr']);
}
