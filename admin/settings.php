<?php
$pageTitle = 'System Settings';

$sidebarNav = '
  <div class="sidebar-section-title">Main</div>
  <a href="/egglandbd/admin/index.php" class="sidebar-link"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>

  <div class="sidebar-section-title">Management</div>
  <a href="/egglandbd/admin/agents.php" class="sidebar-link"><i class="fas fa-user-tie sidebar-icon"></i> Agents</a>
  <a href="/egglandbd/admin/products.php" class="sidebar-link"><i class="fas fa-egg sidebar-icon"></i> Products</a>
  <a href="/egglandbd/admin/prices.php" class="sidebar-link"><i class="fas fa-tags sidebar-icon"></i> Price Management</a>
  <a href="/egglandbd/admin/egg-lots.php" class="sidebar-link"><i class="fas fa-box sidebar-icon"></i> Egg Lots</a>
  <a href="/egglandbd/admin/demands.php" class="sidebar-link"><i class="fas fa-clipboard-list sidebar-icon"></i> Demands</a>

  <div class="sidebar-section-title">Operations</div>
  <a href="/egglandbd/admin/orders.php" class="sidebar-link"><i class="fas fa-shopping-cart sidebar-icon"></i> Orders</a>
  <a href="/egglandbd/admin/deliveries.php" class="sidebar-link"><i class="fas fa-truck sidebar-icon"></i> Deliveries</a>
  <a href="/egglandbd/admin/retailers.php" class="sidebar-link"><i class="fas fa-store sidebar-icon"></i> Retailers</a>
  <a href="/egglandbd/admin/tracking.php" class="sidebar-link"><i class="fas fa-map-marked-alt sidebar-icon"></i> Live Tracking</a>

  <div class="sidebar-section-title">Finance</div>
  <a href="/egglandbd/admin/finance.php" class="sidebar-link"><i class="fas fa-wallet sidebar-icon"></i> Finance</a>
  <a href="/egglandbd/admin/reports.php" class="sidebar-link"><i class="fas fa-chart-bar sidebar-icon"></i> Reports</a>

  <div class="sidebar-section-title">System</div>
  <a href="/egglandbd/admin/settings.php" class="sidebar-link"><i class="fas fa-cog sidebar-icon"></i> Settings</a>
';

ob_start();
?>
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-label">Database Size</div>
    <div class="stat-value" id="setDbSize"><div class="spinner"></div></div>
    <div class="stat-icon"><i class="fas fa-database"></i></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Audit Logs</div>
    <div class="stat-value" id="setAuditCount"><div class="spinner"></div></div>
    <div class="stat-icon"><i class="fas fa-history"></i></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">PHP Version</div>
    <div class="stat-value" id="setPhpVersion"><div class="spinner"></div></div>
    <div class="stat-icon"><i class="fab fa-php"></i></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <i class="fas fa-cog" style="color:var(--maroon)"></i>
      <span class="card-title">General Application Config</span>
    </div>
    <div class="card-body" style="padding:20px">
      <div class="form-group">
        <label class="form-label">Application Name</label>
        <input type="text" class="form-control" id="setAppName" readonly>
      </div>
      <div class="form-group">
        <label class="form-label">Application URL</label>
        <input type="text" class="form-control" id="setAppUrl" readonly>
      </div>
      <div class="form-group">
        <label class="form-label">Currency Symbol</label>
        <input type="text" class="form-control" id="setCurrency" readonly>
      </div>
      <div class="form-group">
        <label class="form-label">Low Stock Threshold</label>
        <input type="text" class="form-control" id="setLowStock" readonly>
      </div>
      <p style="font-size:11px;color:var(--text-muted);margin-top:10px">
        Note: These parameters are defined in the backend configuration file <code>config.php</code> and are currently read-only.
      </p>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <i class="fas fa-server" style="color:var(--gold)"></i>
      <span class="card-title">Server & Environment Info</span>
    </div>
    <div class="card-body" style="padding:20px">
      <div class="form-group">
        <label class="form-label">Database Name</label>
        <input type="text" class="form-control" id="setDbName" readonly>
      </div>
      <div class="form-group">
        <label class="form-label">MySQL Version</label>
        <input type="text" class="form-control" id="setMysqlVersion" readonly>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">APCu Cache</label>
          <div style="margin-top:8px"><span id="setApcu" class="badge">Checking...</span></div>
        </div>
        <div class="form-group">
          <label class="form-label">Debug Mode</label>
          <div style="margin-top:8px"><span id="setDebug" class="badge">Checking...</span></div>
        </div>
      </div>
      <div style="margin-top:24px;border-top:1px solid var(--border-light);padding-top:16px">
        <button class="btn btn-outline btn-block" onclick="clearLocalCache()"><i class="fas fa-broom"></i> Clear Local Browser Cache</button>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();

$scripts = <<<'JS'
<script>
async function loadSettings() {
  const resp = await App.get('admin/settings.php');
  if (!resp?.success) { App.toast('error', 'Error', 'Failed to retrieve system settings'); return; }

  const d = resp.data;

  // Stats
  document.getElementById('setDbSize').textContent = d.db_size;
  document.getElementById('setAuditCount').textContent = d.audit_logs_count.toLocaleString();
  document.getElementById('setPhpVersion').textContent = d.php_version;

  // General settings
  document.getElementById('setAppName').value = d.app_name;
  document.getElementById('setAppUrl').value = d.app_url;
  document.getElementById('setCurrency').value = d.currency;
  document.getElementById('setLowStock').value = d.low_stock_threshold + ' pieces';

  // Server info
  document.getElementById('setDbName').value = d.db_name;
  document.getElementById('setMysqlVersion').value = d.mysql_version;

  // Badges
  const apcuBadge = document.getElementById('setApcu');
  if (d.apcu_enabled) {
    apcuBadge.textContent = 'Active';
    apcuBadge.className = 'badge badge-success';
  } else {
    apcuBadge.textContent = 'Inactive';
    apcuBadge.className = 'badge badge-cancelled';
  }

  const debugBadge = document.getElementById('setDebug');
  if (d.debug_mode) {
    debugBadge.textContent = 'Enabled';
    debugBadge.className = 'badge badge-pending';
  } else {
    debugBadge.textContent = 'Disabled';
    debugBadge.className = 'badge badge-success';
  }
}

function clearLocalCache() {
  localStorage.clear();
  App.toast('success', 'Local Storage Cleared', 'Please re-login if needed.');
  setTimeout(() => window.location.reload(), 1500);
}

loadSettings();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
