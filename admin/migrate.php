<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');

$pdo = getDB();
$message = '';
$messageType = 'success';
$sqlLogs = [];

// Helper to check if a table exists
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

// Helper to check if a column exists
function columnExists($pdo, $table, $column) {
    try {
        $result = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// List of core migration checks & SQLs
$migrations = [
    'buying_price_col' => [
        'name' => 'Add buying_price to products table',
        'check' => function($pdo) { return columnExists($pdo, 'products', 'buying_price'); },
        'sql' => "ALTER TABLE `products` ADD COLUMN `buying_price` DECIMAL(10,2) DEFAULT 0.00 AFTER `unit_type`"
    ],
    'providers_table' => [
        'name' => 'Create providers table',
        'check' => function($pdo) { return tableExists($pdo, 'providers'); },
        'sql' => "CREATE TABLE `providers` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(100) NOT NULL,
          `type` ENUM('company','farm') DEFAULT 'company',
          `status` ENUM('active','inactive') DEFAULT 'active',
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    'warehouse_lots_table' => [
        'name' => 'Create warehouse_lots table',
        'check' => function($pdo) { return tableExists($pdo, 'warehouse_lots'); },
        'sql' => "CREATE TABLE `warehouse_lots` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `provider_id` INT NOT NULL,
          `product_id` INT NOT NULL,
          `qty` DECIMAL(10,2) NOT NULL,
          `buying_price` DECIMAL(10,2) NOT NULL,
          `selling_price` DECIMAL(10,2) NOT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`),
          FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    'product_price_history_table' => [
        'name' => 'Create product_price_history table',
        'check' => function($pdo) { return tableExists($pdo, 'product_price_history'); },
        'sql' => "CREATE TABLE `product_price_history` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `product_id` INT NOT NULL,
          `warehouse_lot_id` INT NULL,
          `old_buying_price` DECIMAL(10,2) DEFAULT 0.00,
          `new_buying_price` DECIMAL(10,2) DEFAULT 0.00,
          `old_selling_price` DECIMAL(10,2) DEFAULT 0.00,
          `new_selling_price` DECIMAL(10,2) DEFAULT 0.00,
          `source` ENUM('lot_addition','product_edit') NOT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    'areas_table' => [
        'name' => 'Create areas table',
        'check' => function($pdo) { return tableExists($pdo, 'areas'); },
        'sql' => "CREATE TABLE `areas` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(100) NOT NULL,
          `status` ENUM('active','inactive') DEFAULT 'active',
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    'demands_table' => [
        'name' => 'Create demands table',
        'check' => function($pdo) { return tableExists($pdo, 'demands'); },
        'sql' => "CREATE TABLE `demands` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `supervisor_id` INT NOT NULL,
          `agent_id` INT NOT NULL,
          `total_qty` DECIMAL(10,2) DEFAULT 0.00,
          `total_amount` DECIMAL(12,2) DEFAULT 0.00,
          `status` ENUM('pending','approved','invoiced','cancelled') DEFAULT 'pending',
          `is_deleted` TINYINT(1) DEFAULT 0,
          `deleted_by` INT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (`supervisor_id`) REFERENCES `supervisors`(`id`),
          FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`),
          FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    'demand_items_table' => [
        'name' => 'Create demand_items table',
        'check' => function($pdo) { return tableExists($pdo, 'demand_items'); },
        'sql' => "CREATE TABLE `demand_items` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `demand_id` INT NOT NULL,
          `product_id` INT NOT NULL,
          `qty` DECIMAL(10,2) NOT NULL,
          `price` DECIMAL(10,2) NOT NULL,
          `amount` DECIMAL(12,2) NOT NULL,
          FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    'dsrs_table' => [
        'name' => 'Create dsrs table',
        'check' => function($pdo) { return tableExists($pdo, 'dsrs'); },
        'sql' => "CREATE TABLE `dsrs` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(100) NOT NULL,
          `phone` VARCHAR(20),
          `status` ENUM('active','inactive') DEFAULT 'active',
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    'dispatches_table' => [
        'name' => 'Create dispatches table',
        'check' => function($pdo) { return tableExists($pdo, 'dispatches'); },
        'sql' => "CREATE TABLE `dispatches` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `dsr_id` INT NOT NULL,
          `destination_type` ENUM('hub','direct') NOT NULL,
          `warehouse_lot_id` INT NOT NULL,
          `qty_dispatched` DECIMAL(10,2) NOT NULL,
          `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
          FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    'dispatch_demands_table' => [
        'name' => 'Create dispatch_demands table',
        'check' => function($pdo) { return tableExists($pdo, 'dispatch_demands'); },
        'sql' => "CREATE TABLE `dispatch_demands` (
          `dispatch_id` INT NOT NULL,
          `demand_id` INT NOT NULL,
          PRIMARY KEY (`dispatch_id`, `demand_id`),
          FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ]
];

// Handle Sync Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync') {
    $executed = 0;
    $errors = 0;
    foreach ($migrations as $key => $migration) {
        if (!$migration['check']($pdo)) {
            try {
                $pdo->exec($migration['sql']);
                $sqlLogs[] = "SUCCESS: " . $migration['name'];
                $executed++;
            } catch (Exception $e) {
                $sqlLogs[] = "ERROR executing '{$migration['name']}': " . $e->getMessage();
                $errors++;
            }
        }
    }
    if ($errors > 0) {
        $message = "Synchronized database with $executed success(es) and $errors error(s).";
        $messageType = 'danger';
    } elseif ($executed > 0) {
        $message = "Database synchronization completed successfully. $executed migration(s) executed.";
        $messageType = 'success';
    } else {
        $message = "Database is already up to date. No migrations executed.";
        $messageType = 'info';
    }
}

// Handle Custom SQL Console Execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute_sql') {
    $rawSql = trim($_POST['custom_sql'] ?? '');
    if (!empty($rawSql)) {
        // Split queries by semicolon to allow multiple execution
        $queries = array_filter(array_map('trim', explode(';', $rawSql)));
        $executed = 0;
        $errors = 0;
        
        foreach ($queries as $query) {
            if (empty($query)) continue;
            try {
                if (stripos($query, 'select') === 0 || stripos($query, 'show') === 0 || stripos($query, 'desc') === 0) {
                    // Fetch results for read queries
                    $stmt = $pdo->query($query);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $rowCount = count($results);
                    $sqlLogs[] = "SUCCESS SELECT query executed. Returned $rowCount row(s).";
                    if ($rowCount > 0) {
                        // Just log the keys and a sample row in log output
                        $sqlLogs[] = "Columns: " . implode(', ', array_keys($results[0]));
                        $sqlLogs[] = "Data preview (1st row): " . json_encode($results[0], JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    $rowsAffected = $pdo->exec($query);
                    $sqlLogs[] = "SUCCESS query executed: " . substr($query, 0, 80) . "... ($rowsAffected rows affected)";
                }
                $executed++;
            } catch (Exception $e) {
                $sqlLogs[] = "ERROR in query '" . substr($query, 0, 50) . "...': " . $e->getMessage();
                $errors++;
            }
        }
        if ($errors > 0) {
            $message = "Executed custom SQL script. $executed query succeeded, $errors failed.";
            $messageType = 'danger';
        } else {
            $message = "Custom SQL script executed successfully. $executed queries run.";
            $messageType = 'success';
        }
    } else {
        $message = "Please paste some SQL commands to execute.";
        $messageType = 'warning';
    }
}

// Fetch tables list
$tablesList = [];
try {
    $stmt = $pdo->query("SHOW TABLES");
    $rawTables = $stmt->fetchAll(PDO::FETCH_NUM);
    foreach ($rawTables as $tbl) {
        $tableName = $tbl[0];
        $countStmt = $pdo->query("SELECT COUNT(*) FROM `$tableName`");
        $rowCount = $countStmt->fetchColumn();
        $tablesList[] = [
            'name' => $tableName,
            'rows' => $rowCount
        ];
    }
} catch (Exception $e) {
    $sqlLogs[] = "Failed fetching tables: " . $e->getMessage();
}

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Database Migration — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<style>
    .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px; }
    .alert-success { background-color: #DEF7EC; color: #03543F; border: 1px solid #BCF0DA; }
    .alert-danger { background-color: #FDE8E8; color: #9B1C1C; border: 1px solid #F8B4B4; }
    .alert-info { background-color: #E1EFFE; color: #1E429F; border: 1px solid #B3D1FF; }
    .alert-warning { background-color: #FEF08A; color: #713F12; border: 1px solid #FDE047; }
    
    .db-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
    @media (max-width: 768px) {
        .db-grid { grid-template-columns: 1fr; }
    }
    
    .terminal-box { background-color: #1F2937; color: #F9FAFB; padding: 16px; border-radius: 12px; font-family: 'Courier New', Courier, monospace; font-size: 13px; max-height: 250px; overflow-y: auto; white-space: pre-wrap; margin-bottom: 20px; border: 1px solid #374151; }
    
    .sql-input { width: 100%; height: 160px; font-family: 'Courier New', Courier, monospace; font-size: 14px; padding: 12px; border-radius: 8px; border: 1px solid #D1D5DB; box-sizing: border-box; background: #F9FAFB; margin-bottom: 12px; outline: none; }
    .sql-input:focus { border-color: #8B0032; background: #fff; }
    
    .sync-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid #F3F4F6; font-size: 13px; }
    .sync-item:last-child { border-bottom: none; }
    .sync-status { font-weight: 600; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
    .status-applied { background-color: #DEF7EC; color: #03543F; }
    .status-pending { background-color: #FEF08A; color: #713F12; }
</style>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div>
        <div class="header-title">Database Migration Console</div>
        <div class="header-subtitle">Synchronize tables and columns across server environments</div>
      </div>
    </div>
    
    <div class="page-content">
      <?php if (!empty($message)): ?>
          <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      
      <?php if (!empty($sqlLogs)): ?>
          <div style="font-size:13px; font-weight:700; color:#374151; margin-bottom:8px;">Console Logs:</div>
          <div class="terminal-box"><?php
              foreach ($sqlLogs as $log) {
                  echo htmlspecialchars($log) . "\n";
              }
          ?></div>
      <?php endif; ?>

      <div class="db-grid">
        <!-- Tables Overview -->
        <div class="card" style="margin-bottom:0;">
          <div class="card-header"><i class="fas fa-table text-primary-color"></i> Active Database Tables</div>
          <div style="max-height: 400px; overflow-y: auto;">
            <table class="tbl" style="margin-bottom: 0;">
              <thead>
                <tr>
                  <th>Table Name</th>
                  <th class="text-right">Row Count</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tablesList as $tbl): ?>
                  <tr>
                    <td class="fw-600"><i class="fas fa-database text-muted" style="margin-right: 6px;"></i> <?= htmlspecialchars($tbl['name']) ?></td>
                    <td class="text-right fw-700 text-muted"><?= number_format($tbl['rows']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Migrations Sync Checker -->
        <div class="card" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between;">
          <div>
            <div class="card-header"><i class="fas fa-sync text-primary-color"></i> Core Migrations Status</div>
            <div style="margin-bottom: 20px;">
              <?php foreach ($migrations as $key => $migration): ?>
                <?php $isApplied = $migration['check']($pdo); ?>
                <div class="sync-item">
                  <span><?= htmlspecialchars($migration['name']) ?></span>
                  <span class="sync-status <?= $isApplied ? 'status-applied' : 'status-pending' ?>">
                    <?= $isApplied ? 'Synchronized' : 'Pending' ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <form method="POST" onsubmit="return confirm('Are you sure you want to run pending migrations? This will alter table structures.');">
              <input type="hidden" name="action" value="sync">
              <button type="submit" class="btn btn-primary" style="width:100%; padding:12px;"><i class="fas fa-cloud-download-alt"></i> Sync Pending Schema Changes</button>
            </form>
          </div>
        </div>
      </div>
      
      <!-- Custom SQL console -->
      <div class="card" style="margin-top: 24px;">
        <div class="card-header"><i class="fas fa-terminal text-primary-color"></i> Direct SQL Console</div>
        <div class="card-body">
          <p style="font-size:12px; color:#6B7280; margin-bottom:12px;"><i class="fas fa-exclamation-triangle text-warning"></i> Caution: Write raw SQL scripts here to manually sync live database state. End queries with a semicolon (<code>;</code>).</p>
          <form method="POST" onsubmit="return confirm('Warning: You are executing raw SQL commands directly on the production database. Are you sure you want to execute?');">
            <input type="hidden" name="action" value="execute_sql">
            <textarea name="custom_sql" class="sql-input" placeholder="e.g. ALTER TABLE `retailers` ADD COLUMN `shop_name` VARCHAR(100) NULL AFTER `name`;"></textarea>
            <button type="submit" class="btn btn-danger"><i class="fas fa-play"></i> Execute SQL Queries</button>
          </form>
        </div>
      </div>
      
    </div>
  </div>
</div>
</body>
</html>
