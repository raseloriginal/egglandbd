<?php
// ============================================================
// EGGLAND BD - Login API
// POST /api/auth/login.php
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if (empty($username) || empty($password)) {
    Response::error('Username and password are required.', 422);
}

$db = Database::getInstance();

// Rate limiting check (APCu optional — degrades gracefully if not installed)
$lockKey = 'login_' . $username;
$attempts = function_exists('apcu_fetch') ? (apcu_fetch($lockKey) ?: 0) : 0;
if (function_exists('apcu_fetch') && $attempts >= LOGIN_MAX_ATTEMPTS) {
    Response::error('Too many failed login attempts. Please try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.', 429);
}

// Fetch user
$stmt = $db->prepare("
    SELECT u.*, r.slug as role, r.name as role_name
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE (u.username = ? OR u.phone = ?) AND u.status = 'active'
    LIMIT 1
");
$stmt->execute([$username, $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    if (function_exists('apcu_store')) {
        apcu_store($lockKey, $attempts + 1, LOGIN_LOCKOUT_MINUTES * 60);
    }
    AuditLog::log('LOGIN_FAILED', 'auth', null, 'username', null, null, ['username' => $username]);
    Response::error('Invalid username or password.', 401);
}

// Clear failed attempts
if (function_exists('apcu_delete')) apcu_delete($lockKey);


// Build JWT payload
$tokenPayload = [
    'uid'      => $user['id'],
    'username' => $user['username'],
    'name'     => $user['name'],
    'role'     => $user['role'],
    'role_id'  => $user['role_id'],
];

// Append role-specific data
if ($user['role'] === 'agent') {
    $s = $db->prepare("SELECT id FROM agents WHERE user_id = ?");
    $s->execute([$user['id']]);
    $ag = $s->fetch();
    if ($ag) $tokenPayload['agent_id'] = $ag['id'];
}
if ($user['role'] === 'sr') {
    $s = $db->prepare("SELECT id, agent_id FROM sr WHERE user_id = ?");
    $s->execute([$user['id']]);
    $sr = $s->fetch();
    if ($sr) { $tokenPayload['sr_id'] = $sr['id']; $tokenPayload['agent_id'] = $sr['agent_id']; }
}
if ($user['role'] === 'dsr') {
    $s = $db->prepare("SELECT id, agent_id FROM dsr WHERE user_id = ?");
    $s->execute([$user['id']]);
    $dsr = $s->fetch();
    if ($dsr) { $tokenPayload['dsr_id'] = $dsr['id']; $tokenPayload['agent_id'] = $dsr['agent_id']; }
}

$token = JWT::encode($tokenPayload);
$refreshToken = JWT::generateRefreshToken();

// Store refresh token
$refreshExpiry = date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRY);
$db->prepare("INSERT INTO user_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
   ->execute([$user['id'], hash('sha256', $refreshToken), $refreshExpiry]);

// Update last login
$db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

// Audit
AuditLog::log('LOGIN', 'auth', $user['id']);

Response::success([
    'token'         => $token,
    'refresh_token' => $refreshToken,
    'expires_in'    => JWT_EXPIRY,
    'user'          => [
        'id'        => $user['id'],
        'name'      => $user['name'],
        'username'  => $user['username'],
        'email'     => $user['email'],
        'phone'     => $user['phone'],
        'avatar'    => $user['avatar'],
        'role'      => $user['role'],
        'role_name' => $user['role_name'],
    ]
], 'Login successful');
