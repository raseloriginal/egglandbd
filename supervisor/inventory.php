<?php
require_once dirname(__DIR__) . '/config/auth.php';
requireRole('supervisor');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Inventory — Supervisor</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css"></head>
<body><div class="layout-wrapper"><?php include dirname(__DIR__) . '/includes/supervisor-sidebar.php'; ?><div class="main-content"><div class="top-header"><div class="header-title">Inventory</div><div class="header-spacer"></div></div><div class="page-content"><div class="coming-soon"><div class="cs-icon">🏪</div><h2>Inventory View</h2><p>Check stock levels and product availability assigned to you.</p><span class="cs-badge">🚧 Coming in Phase 2</span></div></div></div></div></body></html>
