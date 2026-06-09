<?php
// Copy logo to assets if not done already
$logoSrc = __DIR__ . '/assets/images/logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Eggland BD ERP</title>
  <meta name="description" content="Eggland BD — Premium Egg Distribution ERP System Login">
  <meta name="theme-color" content="#8B002D">
  <link rel="manifest" href="/egglandbd/manifest.json">
  <link rel="icon" href="/egglandbd/assets/images/logo.png">
  <link rel="apple-touch-icon" href="/egglandbd/assets/images/logo.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/egglandbd/assets/css/app.css">
  <style>
    .login-page { background: var(--bg); }
    .login-card h2 { font-family: 'Poppins',sans-serif; font-size:26px; font-weight:800; color:var(--text-primary); margin-bottom:6px; }
    .login-card .sub { color:var(--text-muted); font-size:13.5px; margin-bottom:28px; }
    .role-tabs { display:flex; gap:8px; margin-bottom:24px; }
    .role-tab { flex:1; padding:8px; border-radius:var(--radius); border:1.5px solid var(--border); background:white; cursor:pointer; text-align:center; font-size:12px; font-weight:600; color:var(--text-muted); transition:var(--transition); }
    .role-tab.active { border-color:var(--maroon); background:var(--maroon-50); color:var(--maroon); }
    .login-footer { margin-top:20px; text-align:center; font-size:12px; color:var(--text-muted); }
    .floating-egg {
      position:absolute; font-size:80px; opacity:0.08;
      animation: floatEgg 6s ease-in-out infinite;
    }
    @keyframes floatEgg {
      0%,100%{ transform:translateY(0) rotate(-10deg); }
      50%{ transform:translateY(-20px) rotate(10deg); }
    }
  </style>
</head>
<body>

<div class="login-page">
  <!-- Left Brand Panel -->
  <div class="login-left">
    <span class="floating-egg" style="top:10%;left:5%">🥚</span>
    <span class="floating-egg" style="bottom:15%;right:8%;animation-delay:2s;font-size:60px">🥚</span>
    <span class="floating-egg" style="top:50%;left:15%;animation-delay:4s;font-size:40px">🥚</span>

    <div style="position:relative;z-index:1;text-align:center">
      <img src="/egglandbd/assets/images/logo.png" alt="Eggland BD" class="login-brand-logo">
      <div class="login-brand-title">Eggland BD</div>
      <div class="login-brand-sub">Premium Egg Distribution ERP System</div>

      <div class="login-features">
        <div class="login-feature">
          <i class="fas fa-chart-line"></i>
          Complete Sales & Distribution Management
        </div>
        <div class="login-feature">
          <i class="fas fa-map-marked-alt"></i>
          Live Delivery & Route Tracking
        </div>
        <div class="login-feature">
          <i class="fas fa-boxes"></i>
          Real-Time Inventory Control
        </div>
        <div class="login-feature">
          <i class="fas fa-wallet"></i>
          Financial Reporting & Ledger
        </div>
        <div class="login-feature">
          <i class="fas fa-mobile-alt"></i>
          Mobile PWA — Works Offline
        </div>
      </div>
    </div>
  </div>

  <!-- Right Login Panel -->
  <div class="login-right">
    <div class="login-card glass-card" style="padding:36px;border-radius:20px">
      <div style="text-align:center;margin-bottom:24px">
        <div style="display:inline-flex;width:60px;height:60px;background:var(--maroon-50);border-radius:50%;align-items:center;justify-content:center;margin-bottom:12px">
          <i class="fas fa-lock" style="color:var(--maroon);font-size:22px"></i>
        </div>
        <h2>Welcome Back</h2>
        <div class="sub">Sign in to your Eggland BD account</div>
      </div>

      <!-- Role Hint Tabs -->
      <div class="role-tabs">
        <div class="role-tab active" onclick="setDemo('admin','Admin@1234',this)"><i class="fas fa-crown"></i> Admin</div>
        <div class="role-tab" onclick="setDemo('agent1','Admin@1234',this)"><i class="fas fa-user-tie"></i> Agent</div>
        <div class="role-tab" onclick="setDemo('sr1','Admin@1234',this)"><i class="fas fa-handshake"></i> SR</div>
        <div class="role-tab" onclick="setDemo('dsr1','Admin@1234',this)"><i class="fas fa-truck"></i> DSR</div>
      </div>

      <form id="loginForm" onsubmit="doLogin(event)">
        <div class="form-group">
          <label class="form-label"><i class="fas fa-user" style="color:var(--maroon);margin-right:5px"></i>Username</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-user"></i></span>
            <input type="text" id="username" name="username" class="form-control" placeholder="Enter username" value="admin" required autocomplete="username">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label"><i class="fas fa-lock" style="color:var(--maroon);margin-right:5px"></i>Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" value="Admin@1234" required autocomplete="current-password">
            <span class="input-group-text" style="cursor:pointer;border-left:1px solid var(--border)" onclick="togglePass()"><i class="fas fa-eye" id="eyeIcon"></i></span>
          </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
          <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13px">
            <input type="checkbox" style="accent-color:var(--maroon)"> Remember me
          </label>
          <a href="#" style="font-size:12px;color:var(--maroon);text-decoration:none">Forgot password?</a>
        </div>

        <div id="loginError" class="alert alert-danger" style="display:none"></div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" id="loginBtn">
          <i class="fas fa-sign-in-alt"></i> Sign In
        </button>
      </form>

      <div class="login-footer">
        <i class="fas fa-shield-alt" style="color:var(--maroon)"></i>
        Secured with JWT Authentication &bull; Eggland BD v1.0
      </div>
    </div>
  </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
<script src="/egglandbd/assets/js/app.js"></script>
<script>
  // Prevent redirect to login (we ARE on login)
  App.init = function() {
    this.token = localStorage.getItem('eg_token');
    const userData = localStorage.getItem('eg_user');
    if (userData) this.user = JSON.parse(userData);
    if (this.token) redirectByRole(JSON.parse(localStorage.getItem('eg_user')));
    this._initToastContainer();
  };
  App.init();

  function redirectByRole(user) {
    if (!user) return;
    const routes = { admin: '/egglandbd/admin/index.php', agent: '/egglandbd/agent/index.php', sr: '/egglandbd/sr/index.php', dsr: '/egglandbd/dsr/index.php' };
    window.location.href = routes[user.role] || '/egglandbd/admin/index.php';
  }

  function setDemo(username, password, tab) {
    document.getElementById('username').value = username;
    document.getElementById('password').value = password;
    document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
  }

  function togglePass() {
    const inp = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
    else { inp.type = 'password'; icon.className = 'fas fa-eye'; }
  }

  async function doLogin(e) {
    e.preventDefault();
    const btn = document.getElementById('loginBtn');
    const errEl = document.getElementById('loginError');
    errEl.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px"></div> Signing in...';

    const resp = await App.login(
      document.getElementById('username').value.trim(),
      document.getElementById('password').value
    );

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';

    if (resp?.success) {
      App.toast('success', 'Login Successful', `Welcome back, ${resp.data.user.name}!`);
      setTimeout(() => redirectByRole(resp.data.user), 800);
    } else {
      errEl.textContent = resp?.message || 'Invalid credentials. Please try again.';
      errEl.style.display = 'flex';
    }
  }
</script>
</body>
</html>
