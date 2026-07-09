<?php
require_once __DIR__ . '/config/db.php';

try {
    $pdo = getDB();
    if ($pdo) {
        // Run the command to make agent_id nullable in the live database
        $pdo->exec("ALTER TABLE retailers MODIFY agent_id INT NULL");
        
        echo "<h2 style='color: green;'>Database updated successfully!</h2>";
        echo "<p>The `agent_id` column in the `retailers` table has been set to allow NULL values.</p>";
        echo "<p><strong>Important:</strong> Please delete this `update_db.php` file from your server now for security reasons.</p>";
    } else {
        echo "<h2 style='color: red;'>Database Connection Failed.</h2>";
    }
} catch (PDOException $e) {
    echo "<h2 style='color: orange;'>Query executed with message:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>If the error says it's already updated, you are good to go!</p>";
}
?>
