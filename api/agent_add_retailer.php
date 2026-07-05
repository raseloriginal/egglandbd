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
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$lat = $_POST['latitude'] ?? null;
$lng = $_POST['longitude'] ?? null;

if (!$agent_id) {
    echo json_encode(['success' => false, 'message' => 'Agent session not found.']);
    exit;
}

if (empty($name) || empty($phone) || empty($lat) || empty($lng)) {
    echo json_encode(['success' => false, 'message' => 'Name, phone, and location are required.']);
    exit;
}

// Handle image upload
$image_path = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/retailers/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
    
    if (in_array($file_ext, $allowed_exts)) {
        $new_filename = uniqid('retailer_') . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            $image_path = 'uploads/retailers/' . $new_filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed: jpg, jpeg, png, webp.']);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("INSERT INTO retailers (agent_id, name, phone, lat, lng, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$agent_id, $name, $phone, $lat, $lng]);
    
    $retailer_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Retailer added successfully.',
        'retailer' => [
            'id' => $retailer_id,
            'name' => $name,
            'phone' => $phone,
            'lat' => $lat,
            'lng' => $lng,
            'has_order' => 0,
            'has_delivery' => 0
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
