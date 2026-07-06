<?php
require_once __DIR__ . '/config/db.php';

try {
    $pdo = getDB();
    echo "<h1>Database Restructuring Migration</h1>";
    
    // 1. Create warehouse_lot_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `warehouse_lot_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `warehouse_lot_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        `qty` DECIMAL(10,2) NOT NULL,
        `original_qty` DECIMAL(10,2) NOT NULL,
        `buying_price` DECIMAL(10,2) NOT NULL,
        `selling_price` DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>Table 'warehouse_lot_items' created or verified.</p>";

    // 2. Create dispatch_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `dispatch_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `dispatch_id` INT NOT NULL,
        `warehouse_lot_item_id` INT NOT NULL,
        `qty_dispatched` DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`warehouse_lot_item_id`) REFERENCES `warehouse_lot_items`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>Table 'dispatch_items' created or verified.</p>";

    // 3. Migrate warehouse_lots data
    $count = $pdo->query("SELECT COUNT(*) FROM warehouse_lot_items")->fetchColumn();
    if ($count == 0) {
        $cols = $pdo->query("SHOW COLUMNS FROM warehouse_lots LIKE 'product_id'")->fetch();
        if ($cols) {
            $existing_lots = $pdo->query("SELECT * FROM warehouse_lots WHERE product_id IS NOT NULL")->fetchAll();
            foreach ($existing_lots as $lot) {
                $stmt = $pdo->prepare("INSERT INTO warehouse_lot_items (warehouse_lot_id, product_id, qty, original_qty, buying_price, selling_price) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $lot['id'],
                    $lot['product_id'],
                    $lot['qty'],
                    $lot['qty'],
                    $lot['buying_price'],
                    $lot['selling_price']
                ]);
            }
            echo "<p>Migrated " . count($existing_lots) . " warehouse lots to warehouse_lot_items.</p>";
        }
    } else {
        echo "<p>warehouse_lot_items already contains data, skipping lots migration.</p>";
    }

    // 4. Migrate dispatches data
    $count_disp = $pdo->query("SELECT COUNT(*) FROM dispatch_items")->fetchColumn();
    if ($count_disp == 0) {
        $cols_disp = $pdo->query("SHOW COLUMNS FROM dispatches LIKE 'warehouse_lot_id'")->fetch();
        if ($cols_disp) {
            $existing_dispatches = $pdo->query("SELECT * FROM dispatches WHERE warehouse_lot_id IS NOT NULL")->fetchAll();
            $migrated_count = 0;
            foreach ($existing_dispatches as $d) {
                $stmt_item = $pdo->prepare("SELECT id FROM warehouse_lot_items WHERE warehouse_lot_id = ? AND product_id = (SELECT product_id FROM warehouse_lots WHERE id = ?) LIMIT 1");
                $stmt_item->execute([$d['warehouse_lot_id'], $d['warehouse_lot_id']]);
                $lot_item_id = $stmt_item->fetchColumn();
                
                if ($lot_item_id) {
                    $stmt = $pdo->prepare("INSERT INTO dispatch_items (dispatch_id, warehouse_lot_item_id, qty_dispatched) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $d['id'],
                        $lot_item_id,
                        $d['qty_dispatched']
                    ]);
                    $migrated_count++;
                }
            }
            echo "<p>Migrated $migrated_count dispatches to dispatch_items.</p>";
        }
    } else {
        echo "<p>dispatch_items already contains data, skipping dispatches migration.</p>";
    }

    // 5. Alter parent tables to make single-item columns nullable
    $pdo->exec("ALTER TABLE `warehouse_lots` 
        MODIFY `product_id` INT NULL, 
        MODIFY `qty` DECIMAL(10,2) NULL, 
        MODIFY `buying_price` DECIMAL(10,2) NULL, 
        MODIFY `selling_price` DECIMAL(10,2) NULL");
    
    $pdo->exec("ALTER TABLE `dispatches` 
        MODIFY `warehouse_lot_id` INT NULL, 
        MODIFY `qty_dispatched` DECIMAL(10,2) NULL");
    
    echo "<p>Modified parent tables columns to nullable.</p>";
    echo "<h3 style='color: green;'>Migration completed successfully! You can delete this file now.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>Migration failed: " . $e->getMessage() . "</h3>";
}
