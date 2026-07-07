<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

$agentId = $_SESSION['agent_id'] ?? 0;
$pdo = getDB();
$currency = getSetting('currency_symbol', '৳');

$products = $pdo->query("SELECT * FROM products WHERE status='active' ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("SELECT r.*,
      (SELECT COUNT(*) FROM orders o WHERE o.retailer_id=r.id AND o.agent_id=? AND o.status='pending') as has_order,
      (SELECT o.id FROM orders o WHERE o.retailer_id=r.id AND o.agent_id=? AND o.status='pending' ORDER BY o.created_at DESC LIMIT 1) as order_id
    FROM retailers r
    WHERE r.agent_id = ? AND r.status = 'active'
    ORDER BY r.name ASC");
$stmt->execute([$agentId, $agentId, $agentId]);
$retailers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta name="theme-color" content="#8B0032">
    <title>রিটেইলার — এগল্যান্ড বাংলাদেশ</title>
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
              },
              brandbg: '#F0EBE8'
            },
            fontFamily: {
              sans: ['Inter', 'sans-serif'],
            }
          }
        }
      }
    </script>
    <?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>

<style>
.bottom-sheet { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.bottom-sheet.open { transform: translateY(0); }
.bottom-sheet-overlay { transition: opacity 0.3s ease; }
.bottom-sheet-overlay.active { opacity: 1; pointer-events: auto; }
</style>
</head>
<body class="bg-brandbg min-h-full flex flex-col font-sans antialiased text-slate-800 pb-20">
    <header class="bg-primary text-white h-14 flex items-center px-4 sticky top-0 z-50 shadow-md">
        <div class="flex items-center gap-3 w-full">
            <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center text-primary font-black text-sm">E</div>
            <div class="flex-1">
                <h1 class="text-sm font-bold leading-tight">রিটেইলার</h1>
                <p class="text-[10px] text-white/60 font-semibold"><?= count($retailers) ?> জন রিটেইলার</p>
            </div>
            <div class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer transition-colors" onclick="history.back()">
                <i class="fas fa-arrow-left text-sm"></i>
            </div>
        </div>
    </header>

    <div class="bg-white p-3 sticky top-14 z-40 shadow-sm border-b border-slate-100">
        <div class="max-w-2xl mx-auto relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition-all placeholder:text-slate-400" placeholder="নাম বা ফোন নাম্বার দিয়ে খুঁজুন...">
        </div>
    </div>

    <main class="flex-1 max-w-2xl mx-auto w-full p-4">
        <?php if (empty($retailers)): ?>
            <div class="bg-white rounded-2xl p-8 text-center shadow-sm border border-slate-100/50 flex flex-col items-center mt-8">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-warehouse"></i>
                </div>
                <h2 class="text-base font-bold text-slate-800">কোনো রিটেইলার পাওয়া যায়নি</h2>
                <p class="text-xs text-slate-400 mt-1">আপনার এলাকায় এখনো কোনো সচল রিটেইলার যুক্ত করা হয়নি।</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($retailers as $r): ?>
                    <div class="retailer-card bg-white rounded-2xl p-4 shadow-sm border border-slate-100/50 flex items-center gap-4" data-search="<?= strtolower(htmlspecialchars($r['name'] . ' ' . $r['phone'])) ?>">
                        <div class="w-11 h-11 bg-red-50 text-red-600 rounded-xl flex items-center justify-center text-lg shrink-0">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-bold text-slate-900 truncate leading-snug"><?= htmlspecialchars($r['name']) ?></h4>
                            <p class="text-xs text-slate-400 font-medium truncate flex items-center gap-1.5 mt-0.5">
                                <i class="fas fa-map-marker-alt text-slate-300"></i>
                                <span><?= htmlspecialchars($r['address'] ?: 'লোকেশন পিন করা আছে') ?></span>
                            </p>
                            <p class="text-xs text-slate-500 font-bold flex items-center gap-1.5 mt-0.5">
                                <i class="fas fa-phone-alt text-slate-300"></i>
                                <span><?= htmlspecialchars($r['phone'] ?: 'N/A') ?></span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button onclick="handleOrderClick(<?= $r['id'] ?>)" class="px-3 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold hover:bg-primary hover:text-white transition-colors">
                                <i class="fas fa-shopping-cart mr-1"></i> অর্ডার
                            </button>
                            <?php if (!empty($r['phone'])): ?>
                            <a href="tel:<?= htmlspecialchars($r['phone']) ?>" class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-sm hover:bg-green-100 transition-colors">
                                <i class="fas fa-phone"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Bottom Nav -->
    <nav class="bg-white border-t border-slate-100 h-16 fixed bottom-0 left-0 right-0 z-50 flex items-center justify-around px-2 shadow-lg">
        <a href="<?= BASE_URL ?>/agent/dashboard.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-home"></i></span>
            <span>হোম</span>
        </a>
        <a href="<?= BASE_URL ?>/agent/operation.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-map-marked-alt"></i></span>
            <span>ম্যাপ</span>
        </a>
        <a href="<?= BASE_URL ?>/agent/retailers.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-warehouse"></i></span>
            <span>রিটেইলার</span>
        </a>
        <a href="<?= BASE_URL ?>/agent/ledger.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-book"></i></span>
            <span>লেনদেন</span>
        </a>
        <a href="<?= BASE_URL ?>/agent/sales.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-chart-line"></i></span>
            <span>বিক্রি</span>
        </a>
    </nav>

    <script>
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.retailer-card');
            cards.forEach(card => {
                const text = card.getAttribute('data-search');
                if (text.includes(term)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
<!-- ========== BOTTOM SHEETS ========== -->
<!-- Overlay -->
<div class="bottom-sheet-overlay fixed inset-0 bg-black/55 opacity-0 pointer-events-none z-[350]" id="bsOverlay" onclick="closeAllSheets()"></div>

<!-- Sheet 1: New Order -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[80vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetNewOrder">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div>
      <h3 class="text-base font-extrabold text-slate-900" id="soRetailerName">নতুন অর্ডার</h3>
      <p class="text-xs text-slate-400 font-semibold" id="soRetailerAddr">রিটেইলারের ঠিকানা</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <div class="bg-primary/5 rounded-2xl p-4 flex items-center gap-3 border border-primary/10">
      <div class="w-10 h-10 rounded-xl bg-primary text-white flex items-center justify-center text-sm shrink-0"><i class="fas fa-warehouse"></i></div>
      <div>
        <h4 class="text-sm font-bold text-slate-900 leading-snug" id="soRName2">রিটেইলারের নাম</h4>
        <p class="text-xs text-slate-400 font-semibold mt-0.5" id="soRPhone">ফোন</p>
      </div>
    </div>
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">পণ্য ও পরিমাণ সিলেক্ট করুন</div>
    <div class="space-y-3" id="orderProductList">
      <!-- Populated by JS -->
    </div>
    <div class="bg-slate-50 rounded-2xl p-4 flex justify-between items-center border border-slate-100">
      <span class="text-xs font-bold text-slate-500 uppercase">মোট টাকা</span>
      <span class="text-lg font-black text-primary" id="orderTotalVal"><?= $currency ?>0</span>
    </div>
  </div>
  <div class="p-4 border-t border-slate-100 bg-white shrink-0">
    <button class="w-full py-4 bg-gradient-to-r from-primary to-primary-light hover:from-primary-light hover:to-primary text-white rounded-xl text-base font-bold shadow-lg shadow-primary/25 transition-all active:scale-[0.98]" id="btnPlaceOrder" onclick="placeOrder()">
      <i class="fas fa-clipboard-list mr-1.5"></i> অর্ডার কনফার্ম করুন
    </button>
  </div>
</div>

<!-- Sheet 2: Already has order warning -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[80vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetOrderWarning">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div>
      <h3 class="text-base font-extrabold text-slate-900"><i class="fas fa-exclamation-triangle text-amber-500 mr-1.5"></i> অর্ডার ইতিমধ্যে দেওয়া আছে</h3>
      <p class="text-xs text-slate-400 font-semibold" id="warnRetailerName">এই রিটেইলারের একটি অর্ডার পেন্ডিং আছে</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-4 text-xs font-semibold flex gap-2.5">
      <i class="fas fa-exclamation-circle text-amber-600 text-sm mt-0.5"></i>
      <span id="warnText">এই রিটেইলারের একটি অর্ডার ইতিমধ্যে পেন্ডিং আছে। আপনি কি আরেকটি অর্ডার করতে চান?</span>
    </div>
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">চলতি অর্ডারের মালামাল:</div>
    <div id="existingOrderItems" class="space-y-2.5"></div>
  </div>
  <div class="p-4 border-t border-slate-100 bg-white flex gap-3 shrink-0">
    <button onclick="closeAllSheets()" class="flex-1 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm font-bold transition-all">বাতিল করুন</button>
    <button onclick="proceedNewOrder()" class="flex-1 py-3.5 bg-gradient-to-r from-primary to-primary-light hover:from-primary-light hover:to-primary text-white rounded-xl text-sm font-bold shadow-lg shadow-primary/25 transition-all">আবার অর্ডার করুন</button>
  </div>
</div>


<script>

    const RETAILERS = <?= json_encode($retailers, JSON_UNESCAPED_UNICODE) ?>;
    const PRODUCTS  = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
    const CURRENCY  = '<?= $currency ?>';
    const BASE_URL  = '<?= BASE_URL ?>';
    let currentRetailer = null;
    let orderItems = {};

    function handleOrderClick(id) {
        const r = RETAILERS.find(x => x.id == id);
        if (r) {
            if (parseInt(r.has_order) > 0) openOrderWarning(r);
            else openNewOrder(r);
        }
    }
    
    function showToast(msg) { alert(msg); }

// ===== BOTTOM SHEET HELPERS =====
function openSheet(id) {
  closeAllSheets(false);
  document.getElementById('bsOverlay').classList.add('active');
  document.getElementById(id).classList.add('open');
  currentSheet = id;
}

function closeAllSheets(removeOverlay = true) {
  ['sheetNewOrder','sheetOrderWarning','sheetReadySale','sheetDelivery','sheetAddRetailer'].forEach(s => {
    const el = document.getElementById(s);
    if (el) el.classList.remove('open');
  });
  if (removeOverlay) {
    const overlay = document.getElementById('bsOverlay');
    if (overlay) overlay.classList.remove('active');
  }
  currentSheet = null;
}

// ===== NEW ORDER =====
function openNewOrder(retailer) {
  currentRetailer = retailer;
  pendingRetailerId = retailer.id;
  orderItems = {};
  document.getElementById('soRetailerName').textContent = retailer.name;
  document.getElementById('soRetailerAddr').textContent = retailer.address || retailer.area || '';
  document.getElementById('soRName2').textContent = retailer.name;
  document.getElementById('soRPhone').textContent = retailer.phone || 'ফোন নম্বর নেই';
  renderProductList();
  openSheet('sheetNewOrder');
}

function renderProductList() {
  const container = document.getElementById('orderProductList');
  container.innerHTML = '';
  PRODUCTS.forEach(p => {
    const item = document.createElement('div');
    item.className = 'flex flex-col gap-3 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm mb-3';
    item.innerHTML = `
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center text-lg shrink-0">
          <i class="fas fa-egg"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-extrabold text-slate-800 truncate">${p.name}</div>
          <div class="text-[11px] text-slate-500 font-semibold mt-0.5">কেনার মূল্য: ${CURRENCY}${parseFloat(p.buying_price || p.price).toLocaleString()} / ${p.unit_type}</div>
        </div>
      </div>
      
      <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-50">
        <!-- Price Input -->
        <div class="flex-1 relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">${CURRENCY}</span>
          <input class="w-full pl-7 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-800 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-center" 
                 id="price_${p.id}" value="${p.price}" type="number" step="0.01" min="0" oninput="updatePrice(${p.id})">
          <div class="absolute -top-2 left-3 bg-white px-1 text-[9px] font-bold text-slate-400 uppercase tracking-wider">বিক্রয় মূল্য</div>
        </div>
        
        <!-- Qty Input -->
        <div class="flex items-center gap-1.5 bg-slate-100/80 p-1.5 rounded-2xl shrink-0 w-32 border border-slate-200/60 shadow-[inset_0_2px_4px_rgba(0,0,0,0.02)]">
          <button type="button" class="w-8 h-8 rounded-xl bg-white text-slate-700 font-black text-lg shadow-[0_2px_5px_rgba(0,0,0,0.08)] flex items-center justify-center active:scale-75 active:shadow-none hover:text-primary transition-all select-none" onclick="changeQty(${p.id}, -1)">−</button>
          <input class="flex-1 text-center text-base font-black text-primary bg-transparent outline-none w-full" id="qty_${p.id}" value="0" min="0" oninput="updateTotal(${p.id})">
          <button type="button" class="w-8 h-8 rounded-xl bg-gradient-to-b from-primary-light to-primary text-white font-black text-lg shadow-[0_4px_10px_rgba(139,0,50,0.3)] flex items-center justify-center active:scale-75 active:shadow-none transition-all select-none border border-primary-dark" onclick="changeQty(${p.id}, 1)">+</button>
        </div>
      </div>`;
    container.appendChild(item);
  });
  updateOrderTotal();
}

function changeQty(productId, delta) {
  const qtyInput = document.getElementById('qty_' + productId);
  const priceInput = document.getElementById('price_' + productId);
  let price = parseFloat(priceInput.value || '0');
  const prod = PRODUCTS.find(p => p.id == productId);
  const bp = parseFloat(prod.buying_price || 0);
  if (price < bp) {
    price = bp;
    priceInput.value = bp;
  }
  
  let val = parseInt(qtyInput.value || '0') + delta;
  if (val < 0) val = 0;
  qtyInput.value = val;
  
  if (val > 0) orderItems[productId] = {qty: val, price: price};
  else delete orderItems[productId];
  updateOrderTotal();
}

function updatePrice(productId) {
  const qtyInput = document.getElementById('qty_' + productId);
  const priceInput = document.getElementById('price_' + productId);
  const qty = parseInt(qtyInput.value || '0');
  let price = parseFloat(priceInput.value || '0');
  
  const prod = PRODUCTS.find(p => p.id == productId);
  const bp = parseFloat(prod.buying_price || 0);
  if (price < bp) {
    alert('বিক্রয় মূল্য কেনার মূল্য (' + bp + ') এর নিচে হতে পারে না।');
    price = bp;
    priceInput.value = bp;
  }
  
  if (qty > 0) {
    orderItems[productId] = {qty: qty, price: price};
    updateOrderTotal();
  }
}

function updateTotal(productId) {
  const qtyInput = document.getElementById('qty_' + productId);
  const priceInput = document.getElementById('price_' + productId);
  const qty = parseInt(qtyInput.value || '0');
  let price = parseFloat(priceInput.value || '0');
  
  const prod = PRODUCTS.find(p => p.id == productId);
  const bp = parseFloat(prod.buying_price || 0);
  if (price < bp) {
    price = bp;
    priceInput.value = bp;
  }
  
  if (qty > 0) orderItems[productId] = {qty: qty, price: price};
  else delete orderItems[productId];
  updateOrderTotal();
}

function updateOrderTotal() {
  let total = 0;
  Object.values(orderItems).forEach(i => total += i.qty * i.price);
  document.getElementById('orderTotalVal').textContent = CURRENCY + total.toLocaleString('en', {minimumFractionDigits:2});
}

function placeOrder() {
  const items = Object.entries(orderItems).map(([pid, item]) => {
    const prod = PRODUCTS.find(p => p.id == pid);
    const bp = parseFloat(prod.buying_price || 0);
    const finalPrice = item.price < bp ? bp : item.price;
    return {product_id: pid, qty: item.qty, price: finalPrice};
  });
  if (items.length === 0) { alert('অনুগ্রহ করে অন্তত একটি পণ্য সিলেক্ট করুন।'); return; }

  const btn = document.getElementById('btnPlaceOrder');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> অর্ডার প্লেস হচ্ছে...';
  btn.disabled = true;

  fetch(BASE_URL + '/api/orders.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'create', retailer_id: currentRetailer.id, items})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeAllSheets();
      showToast('অর্ডার সফলভাবে সম্পন্ন হয়েছে!', 'success');
      // Update retailer in array
      
      setTimeout(() => location.reload(), 1000);
    } else {
      alert('ত্রুটি: ' + (data.message || 'অর্ডার করতে ব্যর্থ হয়েছে'));
    }
  })
  .catch(() => alert('নেটওয়ার্ক সমস্যা। আবার চেষ্টা করুন।'))
  .finally(() => { btn.innerHTML = '<i class="fas fa-clipboard-list mr-1.5"></i> অর্ডার কনফার্ম করুন'; btn.disabled = false; });
}

// ===== ORDER WARNING =====
function openOrderWarning(retailer) {
  currentRetailer = retailer;
  document.getElementById('warnRetailerName').textContent = retailer.name + ' — চলতি অর্ডার';
  document.getElementById('warnText').textContent = `${retailer.name} এর একটি পেন্ডিং অর্ডার ইতিমধ্যে আছে। আপনি কি আরেকটি অর্ডার করতে চান?`;

  // Fetch existing order items
  fetch(BASE_URL + '/api/orders.php?action=get_items&order_id=' + retailer.order_id)
  .then(r => r.json())
  .then(data => {
    const container = document.getElementById('existingOrderItems');
    container.innerHTML = '';
    if (data.items) {
      data.items.forEach(item => {
        const d = document.createElement('div');
        d.className = 'flex justify-between items-center text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-100';
        d.innerHTML = `<div><div class="font-bold text-slate-800">${item.product_name}</div><div class="text-[10px] text-slate-400 font-semibold mt-0.5">পরিমাণ: ${parseInt(item.qty||0)} ${item.unit_type}</div></div><div class="font-black text-slate-700">${CURRENCY}${(item.qty * item.price).toLocaleString()}</div>`;
        container.appendChild(d);
      });
    }
  }).catch(() => {});

  openSheet('sheetOrderWarning');
}

function proceedNewOrder() {
  closeAllSheets();
  setTimeout(() => openNewOrder(currentRetailer), 350);
}


</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const en2bn = (num) => {
    const banglaDigits = {'0':'০','1':'১','2':'২','3':'৩','4':'৪','5':'৫','6':'৬','7':'৭','8':'৮','9':'৯'};
    return String(num).replace(/[0-9]/g, w => banglaDigits[w]);
  };
  const walkDOM = (node) => {
    if (node.nodeType === 3) {
      if (node.nodeValue.match(/[0-9]/)) {
        node.nodeValue = en2bn(node.nodeValue);
      }
    } else if (node.nodeType === 1 && !['SCRIPT', 'STYLE', 'INPUT', 'TEXTAREA'].includes(node.nodeName)) {
      for (let i = 0; i < node.childNodes.length; i++) {
        walkDOM(node.childNodes[i]);
      }
    }
  };
  walkDOM(document.body);
  const observer = new MutationObserver(mutations => {
    mutations.forEach(m => m.addedNodes.forEach(n => walkDOM(n)));
  });
  observer.observe(document.body, { childList: true, subtree: true });
});
</script>
</body>
</html>
