<?php
require 'config/db.php';
$pdo = getDB();

try {
    $pdo->beginTransaction();

    // Fix 1: Update order status to 'completed' if delivery is completed, due, or partial
    $stmt1 = $pdo->query("UPDATE orders o JOIN deliveries d ON d.order_id = o.id SET o.status = 'completed' WHERE d.status IN ('completed', 'due', 'partial') AND o.status = 'pending'");
    $count1 = $stmt1->rowCount();

    // Fix 2: Update order status to 'cancelled' if delivery is cancelled
    $stmt2 = $pdo->query("UPDATE orders o JOIN deliveries d ON d.order_id = o.id SET o.status = 'cancelled' WHERE d.status = 'cancelled' AND o.status = 'pending'");
    $count2 = $stmt2->rowCount();

    // Fix 3: Cancel orphaned orders that have no deliveries
    $stmt3 = $pdo->query("UPDATE orders o LEFT JOIN deliveries d ON d.order_id = o.id SET o.status = 'cancelled' WHERE d.id IS NULL AND o.status = 'pending'");
    $count3 = $stmt3->rowCount();

    $pdo->commit();

    echo "<h1>Database Fix Completed Successfully!</h1>";
    echo "<p>Fixed stuck completed/due/partial orders: <strong>$count1</strong></p>";
    echo "<p>Fixed cancelled orders: <strong>$count2</strong></p>";
    echo "<p>Fixed orphaned orders (no delivery): <strong>$count3</strong></p>";
    echo "<p style='color:red;'><strong>IMPORTANT:</strong> Please delete this file (fix_live_db.php) from your server now for security.</p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h1>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
