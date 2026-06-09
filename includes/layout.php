<?php
// Shared layout component — included by admin pages
// Usage: include_once __DIR__ . '/../includes/layout.php';
// Set $pageTitle and $activePage before including
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — Eggland BD</title>
  <meta name="description" content="Eggland BD ERP System">
  <meta name="theme-color" content="#8B002D">
  <link rel="manifest" href="/egglandbd/manifest.json">
  <link rel="icon" href="/egglandbd/assets/images/logo.png">
  <link rel="apple-touch-icon" href="/egglandbd/assets/images/logo.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <?php if (!empty($useLeaflet)): ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <?php endif; ?>
  <link rel="stylesheet" href="/egglandbd/assets/css/app.css">
  <?= $extraHead ?? '' ?>
</head>
<body>
<div class="app-layout">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img src="/egglandbd/assets/images/logo.png" alt="Eggland BD" class="sidebar-logo">
      <div class="sidebar-brand-text">
        <div class="sidebar-brand-name">Eggland BD</div>
        <div class="sidebar-brand-sub">ERP System</div>
      </div>
    </div>

    <div class="sidebar-user">
      <div class="sidebar-avatar" id="sidebarAvatar">A</div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name" id="sidebarUserName">Loading...</div>
        <div class="sidebar-user-role" id="sidebarUserRole">Role</div>
      </div>
    </div>

    <nav class="sidebar-nav" id="sidebarNav">
      <?= $sidebarNav ?? '' ?>
    </nav>

    <div style="padding:12px 16px;margin-top:auto;border-top:1px solid rgba(255,255,255,0.1)">
      <a href="#" onclick="App.logout()" class="sidebar-link" style="border-radius:var(--radius);color:rgba(255,255,255,0.6)">
        <i class="fas fa-sign-out-alt sidebar-icon"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- Mobile sidebar overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Main Content -->
  <main class="main-content">
    <!-- Top Header -->
    <header class="top-header">
      <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
      <div class="header-actions">
        <button class="header-icon-btn" onclick="location.reload()" title="Refresh">
          <i class="fas fa-sync-alt"></i>
        </button>
        <button class="header-icon-btn" id="notifBtn" title="Notifications">
          <i class="fas fa-bell"></i>
          <span class="notif-dot" id="notifCount" style="display:none"></span>
        </button>
        <button class="header-icon-btn" onclick="App.logout()" title="Logout">
          <i class="fas fa-sign-out-alt"></i>
        </button>
      </div>
    </header>

    <!-- Page Content -->
    <div class="content-body">
      <?= $content ?? '' ?>
    </div>

    <!-- Mobile Bottom Nav (shown on mobile) -->
    <?php if (!empty($bottomNav)): ?>
    <nav class="bottom-nav"><?= $bottomNav ?></nav>
    <?php endif; ?>
  </main>
</div>

<!-- Confirm Dialog -->
<div class="modal-overlay" id="confirmOverlay">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header">
      <i class="fas fa-exclamation-triangle" style="color:var(--warning);font-size:20px"></i>
      <div class="modal-title" id="confirmTitle">Confirm Action</div>
    </div>
    <div class="modal-body">
      <p id="confirmMsg">Are you sure?</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="App.closeModal('confirmOverlay')">Cancel</button>
      <button class="btn btn-danger" id="confirmBtn">Confirm</button>
    </div>
  </div>
</div>

<!-- Notifications Modal -->
<div class="modal-overlay" id="notifModal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header" style="border-bottom:1px solid var(--border-light)">
      <i class="fas fa-bell" style="color:var(--maroon);font-size:18px"></i>
      <div class="modal-title" style="margin-left:8px">Notifications</div>
      <button class="modal-close" onclick="App.closeModal('notifModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" style="padding:0;max-height:400px;overflow-y:auto" id="notifList">
      <div class="loader"><div class="spinner"></div> Loading...</div>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:space-between;border-top:1px solid var(--border-light);padding:12px 16px">
      <button class="btn btn-ghost btn-sm" onclick="App.markAllNotificationsRead()"><i class="fas fa-check-double"></i> Mark all read</button>
      <button class="btn btn-primary btn-sm" onclick="App.closeModal('notifModal')">Close</button>
    </div>
  </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script src="/egglandbd/assets/js/app.js"></script>
<?php if (!empty($useLeaflet)): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/egglandbd/assets/js/map.js"></script>
<?php endif; ?>
<?php if (!empty($useCharts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php endif; ?>
<?= $scripts ?? '' ?>
</body>
</html>
