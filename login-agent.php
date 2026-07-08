<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';

if (isLoggedIn() && $_SESSION['role'] === 'agent') {
    header('Location: ' . BASE_URL . '/agent/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = loginUser(trim($_POST['username'] ?? ''), $_POST['password'] ?? '', 'agent');
    if ($result['success']) {
        header('Location: ' . BASE_URL . '/agent/dashboard.php');
        exit;
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Agent Login — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          primary: {
            DEFAULT: '#8B0032',
            light: '#A0003A',
            dark: '#5A0020'
          },
          gold: {
            DEFAULT: '#F5A623',
            light: '#F8B646',
            dark: '#D48C16'
          }
        },
        fontFamily: {
          sans: ['Inter', 'sans-serif'],
        }
      }
    }
  }
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-primary-dark via-primary to-[#1A0008] min-h-full flex flex-col items-center justify-center p-6 text-white font-sans antialiased">

<div class="flex flex-col items-center mb-6">
  <div class="w-20 h-20 bg-gold/20 border-2 border-gold/40 rounded-3xl flex items-center justify-center text-4xl mb-4 shadow-lg shadow-black/35 backdrop-blur-sm">
    <i class="fas fa-box text-gold"></i>
  </div>
  <h1 class="text-2xl font-extrabold tracking-tight text-white mb-1">Agent Portal</h1>
  <p class="text-sm text-white/60">Eggland Bangladesh</p>
</div>

<div class="bg-white rounded-2xl p-8 w-full max-w-sm shadow-2xl text-slate-800">
  <h2 class="text-xl font-bold text-slate-900 mb-1">Agent Sign In</h2>
  <p class="text-sm text-slate-400 mb-6">Use your agent credentials to continue.</p>

  <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm font-medium mb-4 flex items-center gap-2">
      <i class="fas fa-times-circle text-red-500"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" autocomplete="off" class="space-y-4">
    <div>
      <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Username</label>
      <input type="text" name="username" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-base text-slate-900 bg-slate-50 focus:bg-white focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition-all placeholder:text-slate-300" placeholder="agent username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocapitalize="none" autocorrect="off">
    </div>
    
    <div>
      <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password</label>
      <div class="relative">
        <input type="password" id="pw" name="password" class="w-full pl-4 pr-12 py-3 border border-slate-200 rounded-xl text-base text-slate-900 bg-slate-50 focus:bg-white focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition-all placeholder:text-slate-300" placeholder="••••••••" required>
        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1 text-lg" onclick="var p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'">
          <i class="fas fa-eye"></i>
        </button>
      </div>
    </div>
    
    <button type="submit" class="w-full py-4 bg-gradient-to-r from-primary to-primary-light hover:from-primary-light hover:to-primary text-white rounded-xl text-base font-bold shadow-lg shadow-primary/25 hover:shadow-primary/30 transition-all active:scale-[0.98]">
      Sign In
    </button>
  </form>
  
  <div class="text-center mt-6 text-xs text-slate-400">
    Demo: <strong class="text-slate-600">agent1</strong> / <strong class="text-slate-600">agent123</strong>
  </div>
</div>

<div class="mt-8 text-sm">
  <a href="<?= BASE_URL ?>/index.php" class="text-white/60 hover:text-gold font-medium transition-colors inline-flex items-center gap-2">
    <i class="fas fa-arrow-left text-xs"></i> Role selection
  </a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  if ("geolocation" in navigator) {
    navigator.geolocation.getCurrentPosition(
      function(position) { console.log("Location permission granted"); },
      function(error) { console.log("Location permission error:", error); },
      { enableHighAccuracy: true }
    );
  }
});
</script>

</body>
</html>
