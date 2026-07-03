<?php
// Admin Sidebar Include
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$u = currentUser();
$firstLetter = strtoupper(substr($u['full_name'] ?? 'A', 0, 1));
?>
<aside class="sidebar" id="adminSidebar">
  <div class="sidebar-logo">
    <?php if (file_exists(dirname(__DIR__) . '/assets/img/logo.png')): ?>
      <img src="/egglandbangladesh/assets/img/logo.png" alt="Logo" style="width:34px;height:34px;object-fit:contain;border-radius:6px;">
    <?php else: ?>
      <div class="sidebar-logo-icon">🥚</div>
    <?php endif; ?>
    <div class="sidebar-logo-text">
      <div class="name">Eggland BD</div>
      <div class="role-tag">Admin Panel</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="nav-section-label">Overview</div>
      <a href="/egglandbangladesh/admin/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Dashboard
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-section-label">Management</div>
      <a href="/egglandbangladesh/admin/supervisors.php" class="nav-item <?= $currentPage === 'supervisors' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-user-shield"></i></span> Supervisors
      </a>
      <a href="/egglandbangladesh/admin/agents.php" class="nav-item <?= $currentPage === 'agents' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-users"></i></span> Agents
      </a>
      <a href="/egglandbangladesh/admin/retailers.php" class="nav-item <?= $currentPage === 'retailers' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-map-marked-alt"></i></span> Retailers Map
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-section-label">Inventory & Finance</div>
      <a href="/egglandbangladesh/admin/products.php" class="nav-item <?= $currentPage === 'products' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-box"></i></span> Products
      </a>
      <a href="/egglandbangladesh/admin/inventory.php" class="nav-item <?= $currentPage === 'inventory' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-warehouse"></i></span> Inventory
      </a>
      <a href="/egglandbangladesh/admin/ledger.php" class="nav-item <?= $currentPage === 'ledger' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-book"></i></span> Ledger
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-section-label">System</div>
      <a href="/egglandbangladesh/admin/settings.php" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-cogs"></i></span> Settings
      </a>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= $firstLetter ?></div>
      <div class="user-info">
        <div class="uname"><?= htmlspecialchars($u['full_name'] ?? 'Admin') ?></div>
        <div class="urole">Administrator</div>
      </div>
      <a href="/egglandbangladesh/logout.php" class="logout-btn" title="Logout">⏻</a>
    </div>
  </div>
</aside>
