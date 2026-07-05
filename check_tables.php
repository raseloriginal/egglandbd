<?php
$pdo = new PDO("mysql:host=localhost;dbname=eggland_bangladesh", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

if (in_array('demands', $tables)) {
    echo "\n--- demands ---\n";
    $stmt2 = $pdo->query("SHOW CREATE TABLE demands");
    $create = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo $create['Create Table'] ?? 'Table demands not found';
} else {
    echo "\nTable 'demands' does not exist in the database.";
}
