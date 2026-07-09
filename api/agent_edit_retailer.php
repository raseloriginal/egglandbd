<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/auth.php';

requireRole('agent');
$pdo = getDB();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$agent_id = $_SESSION['agent_id'] ?? 0;
$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

if (!$agent_id) {
    echo json_encode(['success' => false, 'message' => 'Agent session not found.']);
    exit;
}

if (!$id || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Retailer ID and Name are required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE retailers SET name = ?, phone = ?, address = ? WHERE id = ? AND agent_id = ?");
    $stmt->execute([$name, $phone, $address, $id, $agent_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Retailer updated successfully.'
        ]);
    } else {
        // Might be unchanged or agent doesn't own it
        echo json_encode([
            'success' => true,
            'message' => 'No changes made or retailer not found.'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
