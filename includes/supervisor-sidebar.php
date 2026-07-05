<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$u = currentUser();
$firstLetter = strtoupper(substr($u['full_name'] ?? 'S', 0, 1));
?>
<aside class="sidebar" id="supSidebar" style="--sidebar-bg:#1A0A00;">
  <div class="sidebar-logo">
    <?php if (file_exists(dirname(__DIR__) . '/assets/img/logo.png')): ?>
      <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Logo" style="width:34px;height:34px;object-fit:contain;border-radius:6px;">
    <?php else: ?>
      <div class="sidebar-logo-icon" style="background:#F5A623;color:#8B0032;"><i class="fas fa-egg"></i></div>
    <?php endif; ?>
    <div class="sidebar-logo-text">
      <div class="name">Eggland BD</div>
      <div class="role-tag" style="color:#F5A623;">Supervisor</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="nav-section-label">Overview</div>
      <a href="<?= BASE_URL ?>/supervisor/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>" style="<?= $currentPage === 'dashboard' ? '--sidebar-active:#D4850A' : '' ?>">
        <span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Dashboard
      </a>
    </div>
    <div class="nav-section">
      <div class="nav-section-label">Agent Management</div>
      <a href="<?= BASE_URL ?>/supervisor/agents.php" class="nav-item <?= $currentPage === 'agents' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-users"></i></span> Agents
      </a>
      <a href="<?= BASE_URL ?>/supervisor/agent-ledger.php" class="nav-item <?= $currentPage === 'agent-ledger' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-book"></i></span> Agent Ledger
      </a>
    </div>
    <div class="nav-section">
      <div class="nav-section-label">Operations</div>
      <a href="<?= BASE_URL ?>/supervisor/sales.php" class="nav-item <?= $currentPage === 'sales' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-shopping-cart"></i></span> Sales <span class="nav-badge">Soon</span>
      </a>
      <a href="<?= BASE_URL ?>/supervisor/demand.php" class="nav-item <?= $currentPage === 'demand' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span> Demands
      </a>
      <a href="<?= BASE_URL ?>/supervisor/inventory.php" class="nav-item <?= $currentPage === 'inventory' ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fas fa-warehouse"></i></span> Inventory <span class="nav-badge">Soon</span>
      </a>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar" style="background:#F5A623;color:#5A0020;"><?= $firstLetter ?></div>
      <div class="user-info">
        <div class="uname"><?= htmlspecialchars($u['full_name'] ?? 'Supervisor') ?></div>
        <div class="urole">Supervisor</div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="logout-btn" title="Logout"><i class="fas fa-power-off"></i></a>
    </div>
  </div>
</aside>
