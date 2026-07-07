<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once __DIR__ . '/config/db.php';
$pdo = getDB();
$stmt = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND COLUMN_NAME LIKE '%qty%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
