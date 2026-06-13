<?php
$pageTitle  = 'Price Management';
$useCharts  = true;

$sidebarNav = '
  <div class="sidebar-section-title">Main</div>
  <a href="/egglandbd/admin/index.php" class="sidebar-link"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>

  <div class="sidebar-section-title">Management</div>
  <a href="/egglandbd/admin/agents.php" class="sidebar-link"><i class="fas fa-user-tie sidebar-icon"></i> Agents</a>
  <a href="/egglandbd/admin/products.php" class="sidebar-link"><i class="fas fa-egg sidebar-icon"></i> Products</a>
  <a href="/egglandbd/admin/prices.php" class="sidebar-link active"><i class="fas fa-tags sidebar-icon"></i> Price Management</a>
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

<!-- ── Price History Chart ─────────────────────────────── -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header" style="flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:center;gap:10px">
      <i class="fas fa-chart-line" style="color:var(--maroon);font-size:18px"></i>
      <span class="card-title">Egg Price History</span>
    </div>
    <div style="display:flex;align-items:center;gap:10px;margin-left:auto;flex-wrap:wrap">

      <div style="display:flex;gap:6px">
        <button class="btn btn-sm btn-ghost active" id="btn7" onclick="setChartDays(7,this)">7D</button>
        <button class="btn btn-sm btn-ghost" id="btn30" onclick="setChartDays(30,this)">30D</button>
        <button class="btn btn-sm btn-ghost" id="btn90" onclick="setChartDays(90,this)">90D</button>
      </div>
    </div>
  </div>
  <div class="card-body" style="padding:20px">
    <div id="chartEmpty" style="display:none;text-align:center;padding:40px;color:var(--text-muted)">
      <i class="fas fa-chart-line" style="font-size:36px;display:block;margin-bottom:10px;opacity:0.3"></i>
      No price change history yet. Update some prices to see the chart.
    </div>
    <div style="height:400px">
      <canvas id="priceChart"></canvas>
    </div>
  </div>
</div>

<!-- ── Toolbar ───────────────────────────────────────────── -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <div style="flex:1;min-width:200px;position:relative">
    <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted)"></i>
    <input type="text" id="priceSearch" class="form-control" placeholder="Search products…" style="padding-left:36px" oninput="filterCards()">
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <span style="font-size:13px;color:var(--text-muted)" id="productCount"></span>
    <button class="btn btn-primary" onclick="saveAllPrices()">
      <i class="fas fa-save"></i> Save All Changes
    </button>
  </div>
</div>

<!-- ── Product Cards Grid ─────────────────────────────────── -->
<div id="priceCardsGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px">
  <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted)">
    <div class="spinner" style="margin:auto;margin-bottom:12px"></div>
    Loading products…
  </div>
</div>

<!-- ── Save Confirmation (floating) ──────────────────────── -->
<div id="saveBar" style="display:none;position:fixed;bottom:24px;right:24px;z-index:9999">
  <div style="background:var(--maroon);color:white;padding:14px 20px;border-radius:var(--radius);box-shadow:0 8px 32px rgba(139,0,45,0.35);display:flex;align-items:center;gap:14px;font-weight:600">
    <i class="fas fa-exclamation-circle"></i>
    <span id="saveBarText">Changes pending</span>
    <button class="btn btn-sm" style="background:white;color:var(--maroon);font-weight:700" onclick="saveAllPrices()">
      <i class="fas fa-save"></i> Save All
    </button>
    <button onclick="resetAllChanges()" style="background:none;border:none;color:rgba(255,255,255,0.7);cursor:pointer;font-size:18px;padding:0;line-height:1">&times;</button>
  </div>
</div>

<?php
$content = ob_get_clean();

$scripts = <<<'JS'
<script>
let allProducts   = [];
let chartInstance = null;
let chartDays     = 7;
let pendingChanges = {}; // { productId: { buying_price, selling_price } }

// ── Load Products ─────────────────────────────────────────
async function loadPriceProducts() {
  const resp = await App.get('admin/prices.php');
  if (!resp?.success) { App.toast('error','Error','Failed to load products'); return; }

  allProducts = resp.data;



  renderCards(allProducts);
  document.getElementById('productCount').textContent = `${allProducts.length} products`;

  // Load chart
  loadChart();
}

// ── Render Cards ──────────────────────────────────────────
function renderCards(products) {
  const grid = document.getElementById('priceCardsGrid');
  if (!products.length) {
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted)">
      <i class="fas fa-search" style="font-size:36px;display:block;margin-bottom:10px;opacity:0.3"></i>No products found</div>`;
    return;
  }

  grid.innerHTML = products.map(p => {
    const imgHtml = p.image
      ? `<img src="${p.image}" alt="${p.name}" style="width:100%;height:100%;object-fit:cover">`
      : `<span style="font-size:28px">🥚</span>`;

    const pending = pendingChanges[p.id];
    const buyVal  = pending?.buying_price  ?? p.buying_price;
    const sellVal = pending?.selling_price ?? p.selling_price;
    const hasPending = !!pending;

    return `
    <div class="card price-card${hasPending ? ' has-change' : ''}" id="card-${p.id}" style="transition:box-shadow .2s;border:2px solid ${hasPending ? 'var(--maroon)' : 'transparent'}">
      <div class="card-body" style="padding:18px">

        <!-- Product Header -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
          <div style="width:56px;height:56px;border-radius:var(--radius-sm);overflow:hidden;background:var(--maroon-50);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            ${imgHtml}
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${p.name}">${p.name}</div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px">${p.category_name || 'Uncategorised'} &bull; ${p.unit}</div>
          </div>
          ${hasPending ? `<span style="background:var(--maroon);color:white;font-size:10px;padding:2px 8px;border-radius:20px;white-space:nowrap">Modified</span>` : ''}
        </div>

        <!-- Price Inputs -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div>
            <label style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px">
              <i class="fas fa-arrow-down" style="color:var(--info)"></i> Buying ৳
            </label>
            <input type="number" step="0.01" min="0"
              class="form-control price-input"
              id="buy-${p.id}"
              value="${buyVal}"
              placeholder="${p.buying_price}"
              onchange="markChanged(${p.id},'buying_price',this.value)"
              style="font-weight:700;font-size:15px;text-align:center;padding:8px">
            <div style="font-size:10px;color:var(--text-muted);text-align:center;margin-top:3px">
              Current: ৳${parseFloat(p.buying_price).toFixed(2)}
            </div>
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px">
              <i class="fas fa-arrow-up" style="color:var(--maroon)"></i> Selling ৳
            </label>
            <input type="number" step="0.01" min="0"
              class="form-control price-input"
              id="sell-${p.id}"
              value="${sellVal}"
              placeholder="${p.selling_price}"
              onchange="markChanged(${p.id},'selling_price',this.value)"
              style="font-weight:700;font-size:15px;text-align:center;padding:8px;color:var(--maroon)">
            <div style="font-size:10px;color:var(--text-muted);text-align:center;margin-top:3px">
              Current: ৳${parseFloat(p.selling_price).toFixed(2)}
            </div>
          </div>
        </div>

        <!-- Margin indicator -->
        <div style="margin-top:12px;padding:8px 12px;background:var(--surface);border-radius:var(--radius-sm);display:flex;justify-content:space-between;align-items:center" id="margin-${p.id}">
          <span style="font-size:11px;color:var(--text-muted)">Margin</span>
          <span style="font-size:13px;font-weight:700;color:var(--success)" id="marginVal-${p.id}">
            ৳${(parseFloat(sellVal) - parseFloat(buyVal)).toFixed(2)}
            <span style="font-size:10px;font-weight:400;color:var(--text-muted)">
              (${buyVal > 0 ? (((sellVal - buyVal) / buyVal) * 100).toFixed(1) : '0'}%)
            </span>
          </span>
        </div>

        <!-- Update Button -->
        <button class="btn btn-primary btn-block" style="margin-top:12px;width:100%" onclick="saveSingleProduct(${p.id})">
          <i class="fas fa-sync-alt"></i> Update Price
        </button>
      </div>
    </div>`;
  }).join('');
}

// ── Mark Change ───────────────────────────────────────────
function markChanged(productId, field, value) {
  if (!pendingChanges[productId]) pendingChanges[productId] = {};
  pendingChanges[productId][field] = parseFloat(value);

  // Update margin live
  const p     = allProducts.find(x => x.id == productId);
  const buy   = parseFloat(document.getElementById(`buy-${productId}`).value)  || p.buying_price;
  const sell  = parseFloat(document.getElementById(`sell-${productId}`).value) || p.selling_price;
  const margin = sell - buy;
  const pct    = buy > 0 ? ((margin / buy) * 100).toFixed(1) : '0';

  const mv = document.getElementById(`marginVal-${productId}`);
  if (mv) {
    mv.style.color = margin >= 0 ? 'var(--success)' : 'var(--danger)';
    mv.innerHTML = `৳${margin.toFixed(2)} <span style="font-size:10px;font-weight:400;color:var(--text-muted)">(${pct}%)</span>`;
  }

  // Highlight card
  const card = document.getElementById(`card-${productId}`);
  if (card) card.style.borderColor = 'var(--maroon)';

  updateSaveBar();
}

function updateSaveBar() {
  const count = Object.keys(pendingChanges).length;
  const bar   = document.getElementById('saveBar');
  const txt   = document.getElementById('saveBarText');
  if (count > 0) {
    bar.style.display = 'block';
    txt.textContent   = `${count} product${count > 1 ? 's' : ''} pending save`;
  } else {
    bar.style.display = 'none';
  }
}

function resetAllChanges() {
  pendingChanges = {};
  renderCards(getFilteredProducts());
  updateSaveBar();
}

// ── Save Single ───────────────────────────────────────────
async function saveSingleProduct(id) {
  const buy  = parseFloat(document.getElementById(`buy-${id}`).value);
  const sell = parseFloat(document.getElementById(`sell-${id}`).value);

  if (isNaN(buy) || isNaN(sell) || buy <= 0 || sell <= 0) {
    App.toast('warning','Invalid','Enter valid prices'); return;
  }

  const resp = await App.put('admin/prices.php', { id, buying_price: buy, selling_price: sell });
  if (resp?.success) {
    App.toast('success','Updated!', allProducts.find(p=>p.id==id)?.name || '');
    delete pendingChanges[id];

    // Update local data
    const idx = allProducts.findIndex(p => p.id == id);
    if (idx !== -1) {
      allProducts[idx].buying_price  = buy;
      allProducts[idx].selling_price = sell;
    }

    const card = document.getElementById(`card-${id}`);
    if (card) card.style.borderColor = 'transparent';
    updateSaveBar();
    loadChart();
  } else {
    App.toast('error','Failed', resp?.message);
  }
}

// ── Save All ──────────────────────────────────────────────
async function saveAllPrices() {
  if (!Object.keys(pendingChanges).length) {
    App.toast('info','No Changes','Edit prices first'); return;
  }

  const products = Object.entries(pendingChanges).map(([id, prices]) => ({
    id: parseInt(id), ...prices
  }));

  const resp = await App.put('admin/prices.php', { products });
  if (resp?.success) {
    App.toast('success','All Saved!', `${resp.data.updated} product(s) updated`);

    // Update local data
    products.forEach(item => {
      const idx = allProducts.findIndex(p => p.id == item.id);
      if (idx !== -1) {
        if (item.buying_price)  allProducts[idx].buying_price  = item.buying_price;
        if (item.selling_price) allProducts[idx].selling_price = item.selling_price;
      }
    });

    pendingChanges = {};
    renderCards(getFilteredProducts());
    updateSaveBar();
    loadChart();
  } else {
    App.toast('error','Failed', resp?.message);
  }
}

// ── Filter ────────────────────────────────────────────────
function getFilteredProducts() {
  const q = document.getElementById('priceSearch').value.toLowerCase();
  return q ? allProducts.filter(p => p.name.toLowerCase().includes(q)) : allProducts;
}

function filterCards() {
  renderCards(getFilteredProducts());
}

// ── Chart ─────────────────────────────────────────────────
async function loadChart() {
  const params = { history: 1, days: chartDays };
  const resp = await App.get('admin/prices.php', params);
  if (!resp?.success) return;

  const rows = resp.data;
  const canvas = document.getElementById('priceChart');
  const emptyEl = document.getElementById('chartEmpty');

  if (!rows.length) {
    canvas.style.display = 'none';
    emptyEl.style.display = 'block';
    return;
  }

  canvas.style.display = 'block';
  emptyEl.style.display = 'none';

  // Extract unique dates and sort them
  const uniqueDates = [...new Set(rows.map(r => r.date))].sort();

  // Group data by product
  const products = {};
  rows.forEach(r => {
    if (!products[r.product_id]) {
      products[r.product_id] = { name: r.product_name, dataByDate: {} };
    }
    products[r.product_id].dataByDate[r.date] = parseFloat(r.selling_price);
  });

  const colors = ['#8B002D', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#f43f5e', '#6366f1', '#06b6d4'];
  
  const datasets = Object.values(products).map((p, i) => {
    const color = colors[i % colors.length];
    const data = [];
    let lastPrice = null; // Start with null, will only plot connected lines if data exists

    uniqueDates.forEach(date => {
      if (p.dataByDate[date] !== undefined) {
        lastPrice = p.dataByDate[date];
      }
      data.push(lastPrice);
    });

    return {
      label: p.name,
      data: data,
      backgroundColor: color,
      borderRadius: 4,
      barPercentage: 0.8,
      categoryPercentage: 0.9
    };
  });

  if (chartInstance) chartInstance.destroy();

  chartInstance = new Chart(canvas.getContext('2d'), {
    type: 'bar',
    data: {
      labels: uniqueDates,
      datasets: datasets
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          position: 'top',
          labels: { usePointStyle: true, pointStyle: 'circle', padding: 15, font: { size: 11, weight: '500' } }
        },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.dataset.label}: ৳${ctx.parsed.y.toFixed(2)}`
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(0,0,0,0.04)' },
          ticks: { font: { size: 11 }, color: '#6b7280', maxTicksLimit: 10 }
        },
        y: {
          grid: { color: 'rgba(0,0,0,0.05)' },
          ticks: {
            font: { size: 11 }, color: '#6b7280',
            callback: v => '৳' + v.toLocaleString()
          }
        }
      }
    }
  });
}

function setChartDays(days, btn) {
  chartDays = days;
  document.querySelectorAll('#btn7,#btn30,#btn90').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  loadChart();
}

// ── Init ──────────────────────────────────────────────────
loadPriceProducts();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
