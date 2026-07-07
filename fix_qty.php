<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once __DIR__ . '/config/db.php';
$pdo = getDB();

// Find all columns with 'qty' in their name
$stmt = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE 
                     FROM information_schema.columns 
                     WHERE table_schema = DATABASE() AND COLUMN_NAME LIKE '%qty%'");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    if (strpos(strtolower($col['COLUMN_TYPE']), 'decimal') !== false) {
        $table = $col['TABLE_NAME'];
        $column = $col['COLUMN_NAME'];
        echo "Altering $table.$column...\n";
        $pdo->exec("ALTER TABLE `$table` MODIFY `$column` FLOAT NOT NULL DEFAULT 0");
    }
}
echo "Done.\n";
