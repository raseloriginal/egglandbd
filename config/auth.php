<?php
if (session_status() === PHP_SESSION_NONE) {
    // Determine context path to isolate session names
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/admin/') !== false || strpos($uri, 'login-admin.php') !== false) {
        session_name('eggland_admin_session');
    } elseif (strpos($uri, '/supervisor/') !== false || strpos($uri, 'login-supervisor.php') !== false) {
        session_name('eggland_supervisor_session');
    } elseif (strpos($uri, '/agent/') !== false || strpos($uri, 'login-agent.php') !== false) {
        session_name('eggland_agent_session');
    } else {
        session_name('eggland_session');
    }
    session_start();
}

/**
 * Require a specific role to access the page.
 * Redirects to appropriate login if not authenticated.
 */
function requireRole($role) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: /egglandbangladesh/login-' . $role . '.php');
        exit;
    }
    if ($_SESSION['role'] !== $role) {
        // Wrong role — redirect to their own login
        header('Location: /egglandbangladesh/login-' . $_SESSION['role'] . '.php');
        exit;
    }
    if (isset($_SESSION['status']) && $_SESSION['status'] === 'inactive') {
        session_destroy();
        header('Location: /egglandbangladesh/login-' . $role . '.php?error=inactive');
        exit;
    }
}

/**
 * Login a user: validate credentials, set session.
 */
function loginUser($username, $password, $role) {
    require_once __DIR__ . '/db.php';
    $pdo = getDB();
    if (!$pdo) return ['success' => false, 'message' => 'Database connection failed.'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$username, $role]);
    $user = $stmt->fetch();

    // Demo: password is 'password' for all demo accounts
    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    // Set session
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['status']    = $user['status'];

    // Load role-specific profile IDs
    if ($role === 'supervisor') {
        $s = $pdo->prepare("SELECT id FROM supervisors WHERE user_id = ? LIMIT 1");
        $s->execute([$user['id']]);
        $sup = $s->fetch();
        $_SESSION['supervisor_id'] = $sup ? $sup['id'] : null;
    }
    if ($role === 'agent') {
        $a = $pdo->prepare("SELECT id, supervisor_id FROM agents WHERE user_id = ? LIMIT 1");
        $a->execute([$user['id']]);
        $ag = $a->fetch();
        $_SESSION['agent_id']      = $ag ? $ag['id'] : null;
        $_SESSION['supervisor_id'] = $ag ? $ag['supervisor_id'] : null;
    }

    return ['success' => true];
}

/**
 * Destroy session and log out.
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Get current user session data.
 */
function currentUser() {
    return $_SESSION ?? [];
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
