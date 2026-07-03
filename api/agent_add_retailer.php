<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

checkAuth('agent');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$agent_id = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$shop_name = trim($_POST['shop_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

if (empty($name) || empty($phone) || empty($latitude) || empty($longitude)) {
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
    $stmt = $pdo->prepare("INSERT INTO retailers (name, shop_name, phone, image_path, latitude, longitude, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$name, $shop_name, $phone, $image_path, $latitude, $longitude, $agent_id]);
    
    $retailer_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Retailer added successfully.',
        'retailer' => [
            'id' => $retailer_id,
            'name' => $name,
            'shop_name' => $shop_name,
            'phone' => $phone,
            'image_path' => $image_path,
            'latitude' => $latitude,
            'longitude' => $longitude
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
