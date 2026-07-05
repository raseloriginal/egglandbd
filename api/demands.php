<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

$body = [];
if ($method === 'POST' && empty($_POST)) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? $action;
}

$u = currentUser();
$userId = $u['user_id'] ?? 0;
$role = $u['role'] ?? '';

// CREATE OR UPDATE DEMAND
if ($method === 'POST' && ($action === 'create' || $action === 'update')) {
    $agentId = (int)($body['agent_id'] ?? 0);
    $items = $body['items'] ?? [];
    $demandId = (int)($body['demand_id'] ?? 0);

    if (!$agentId || empty($items)) {
        echo json_encode(['success'=>false,'message'=>'Missing agent or items']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $totalQty = 0;
        $totalAmount = 0;
        foreach ($items as $item) {
            $totalQty += (float)$item['qty'];
            $totalAmount += ((float)$item['qty']) * ((float)$item['price']);
        }

        if ($action === 'create') {
            // Find supervisor ID
            $stmt = $pdo->prepare("SELECT id FROM supervisors WHERE user_id = ?");
            $stmt->execute([$userId]);
            $sup = $stmt->fetch();
            $supervisorId = $sup ? $sup['id'] : null;

            if (!$supervisorId && $role === 'supervisor') {
                throw new Exception("Supervisor profile not found");
            }
            if ($role === 'admin') {
                // if admin is creating it, we might just put NULL or a dummy supervisor
                // Let's assume admins can create too, or it's primarily for supervisors
                $supervisorId = $body['supervisor_id'] ?? null;
            }

            $pdo->prepare("INSERT INTO demands (supervisor_id, agent_id, total_qty, total_amount, status) VALUES (?,?,?,?,'pending')")
                ->execute([$supervisorId, $agentId, $totalQty, $totalAmount]);
            $demandId = $pdo->lastInsertId();
        } else {
            $pdo->prepare("UPDATE demands SET agent_id=?, total_qty=?, total_amount=? WHERE id=?")
                ->execute([$agentId, $totalQty, $totalAmount, $demandId]);
            $pdo->prepare("DELETE FROM demand_items WHERE demand_id=?")->execute([$demandId]);
        }

        foreach ($items as $item) {
            $amt = ((float)$item['qty']) * ((float)$item['price']);
            $pdo->prepare("INSERT INTO demand_items (demand_id, product_id, qty, price, amount) VALUES (?,?,?,?,?)")
                ->execute([$demandId, (int)$item['product_id'], (float)$item['qty'], (float)$item['price'], $amt]);
        }

        $pdo->commit();
        echo json_encode(['success'=>true,'demand_id'=>$demandId,'message'=>($action==='create'?'Demand saved':'Demand updated')]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// DELETE DEMAND (Soft delete)
if ($method === 'POST' && $action === 'delete') {
    $demandId = (int)($body['demand_id'] ?? 0);
    if (!$demandId) { echo json_encode(['success'=>false,'message'=>'Missing demand ID']); exit; }
    
    try {
        $pdo->prepare("UPDATE demands SET is_deleted=1, deleted_by=? WHERE id=?")->execute([$userId, $demandId]);
        echo json_encode(['success'=>true,'message'=>'Demand deleted']);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// UPDATE STATUS
if ($method === 'POST' && $action === 'update_status') {
    if ($role !== 'admin') { echo json_encode(['success'=>false,'message'=>'Admin access required']); exit; }
    
    $demandId = (int)($body['demand_id'] ?? 0);
    $status = $body['status'] ?? '';
    if (!$demandId || !$status) { echo json_encode(['success'=>false,'message'=>'Missing inputs']); exit; }
    
    try {
        $pdo->prepare("UPDATE demands SET status=? WHERE id=?")->execute([$status, $demandId]);
        echo json_encode(['success'=>true,'message'=>'Status updated']);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// GET DEMAND DETAILS (for edit/view)
if ($method === 'GET' && $action === 'get') {
    $demandId = (int)($_GET['demand_id'] ?? 0);
    if (!$demandId) { echo json_encode(['success'=>false,'message'=>'No demand ID']); exit; }
    
    $demand = $pdo->prepare("SELECT * FROM demands WHERE id = ?");
    $demand->execute([$demandId]);
    $dData = $demand->fetch();
    
    if (!$dData) { echo json_encode(['success'=>false,'message'=>'Demand not found']); exit; }
    
    $stmt = $pdo->prepare("
        SELECT di.*, p.name as product_name, p.unit_type
        FROM demand_items di
        JOIN products p ON p.id = di.product_id
        WHERE di.demand_id = ?
    ");
    $stmt->execute([$demandId]);
    $dData['items'] = $stmt->fetchAll();
    
    echo json_encode(['success'=>true,'demand'=>$dData]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);