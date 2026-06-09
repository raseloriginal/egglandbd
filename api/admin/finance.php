<?php
// ============================================================
// EGGLAND BD - Finance API (Deposits + Expenses)
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/audit.php';

$user = requireAny();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$type = $_GET['type'] ?? 'deposits';

// ── GET ────────────────────────────────────────────────────
if ($method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = max(1, min(100, (int)($_GET['page_size'] ?? 20)));
    $offset = ($page - 1) * $pageSize;
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $search = trim($_GET['search'] ?? '');

    if ($type === 'deposits') {
        $where = " WHERE d.deposited_at BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];

        if ($user['role'] === 'agent') {
            $where .= " AND d.agent_id = ?";
            $params[] = $user['agent_id'];
        }

        if ($search) { $where .= " AND u.name LIKE ?"; $params[] = "%$search%"; }

        $count = $db->prepare("SELECT COUNT(*) FROM deposits d JOIN agents ag ON ag.id = d.agent_id JOIN users u ON u.id = ag.user_id $where");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $stmt = $db->prepare("
            SELECT d.*, u.name as agent_name
            FROM deposits d
            JOIN agents ag ON ag.id = d.agent_id
            JOIN users u ON u.id = ag.user_id
            $where
            ORDER BY d.created_at DESC
            LIMIT $pageSize OFFSET $offset
        ");
        $stmt->execute($params);
        Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
    }

    if ($type === 'expenses') {
        $count = $db->prepare("SELECT COUNT(*) FROM expenses WHERE expense_date BETWEEN ? AND ?");
        $count->execute([$dateFrom, $dateTo]);
        $total = (int)$count->fetchColumn();

        $stmt = $db->prepare("
            SELECT e.*, u.name as added_by_name
            FROM expenses e
            LEFT JOIN users u ON u.id = e.added_by
            WHERE e.expense_date BETWEEN ? AND ?
            ORDER BY e.expense_date DESC
            LIMIT $pageSize OFFSET $offset
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
    }

    if ($type === 'collections') {
        $count = $db->prepare("SELECT COUNT(*) FROM cash_collections WHERE collected_at BETWEEN ? AND ?");
        $count->execute([$dateFrom, $dateTo]);
        $total = (int)$count->fetchColumn();

        $stmt = $db->prepare("
            SELECT cc.*, r.name as retailer_name, u.name as agent_name
            FROM cash_collections cc
            JOIN retailers r ON r.id = cc.retailer_id
            JOIN agents ag ON ag.id = cc.agent_id
            JOIN users u ON u.id = ag.user_id
            WHERE cc.collected_at BETWEEN ? AND ?
            ORDER BY cc.created_at DESC
            LIMIT $pageSize OFFSET $offset
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
    }

    Response::error('Unknown type', 400);
}

// ── POST ───────────────────────────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $type = $body['type'] ?? '';

    if ($type === 'deposit') {
        requireAdmin();
        $stmt = $db->prepare("
            INSERT INTO deposits (agent_id, amount, bank_name, account_number, reference, deposited_at, notes, added_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $body['agent_id'], $body['amount'], $body['bank_name'] ?? null,
            $body['account_number'] ?? null, $body['reference'] ?? null,
            $body['deposited_at'] ?? date('Y-m-d'), $body['notes'] ?? null, $user['uid']
        ]);
        $id = $db->lastInsertId();
        AuditLog::log('CREATE', 'deposit', $user['uid'], 'deposit', $id, null, null, $body);
        Response::success(['id' => $id], 'Deposit recorded');
    }

    if ($type === 'expense') {
        requireAdmin();
        if (empty($body['description']) || empty($body['amount'])) Response::error('Required fields missing', 422);
        $stmt = $db->prepare("
            INSERT INTO expenses (category, description, amount, expense_date, reference, notes, added_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $body['category'] ?? 'other', $body['description'], $body['amount'],
            $body['expense_date'] ?? date('Y-m-d'), $body['reference'] ?? null,
            $body['notes'] ?? null, $user['uid']
        ]);
        Response::success(['id' => $db->lastInsertId()], 'Expense recorded');
    }

    Response::error('Unknown type', 400);
}

// ── PUT ────────────────────────────────────────────────────
if ($method === 'PUT') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($body['action'] === 'confirm') {
        $db->prepare("UPDATE deposits SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?")
           ->execute([$user['uid'], $id]);
        Response::success(null, 'Deposit confirmed');
    }

    Response::error('Unknown action');
}
