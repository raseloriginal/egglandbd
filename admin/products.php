<?php
$pageTitle = 'Product Management';

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
<div class="card" style="margin-bottom:20px">
  <div class="toolbar">
    <div class="toolbar-search">
      <i class="fas fa-search toolbar-search-icon"></i>
      <input type="text" class="form-control" id="searchInput" placeholder="Search products..." oninput="debounce(loadProducts,400)()">
    </div>
    <select class="form-control" style="width:160px" id="categoryFilter" onchange="loadProducts()">
      <option value="">All Categories</option>
    </select>
    <select class="form-control" style="width:120px" id="statusFilter" onchange="loadProducts()">
      <option value="active">Active</option>
      <option value="">All</option>
      <option value="inactive">Inactive</option>
    </select>
    <div class="toolbar-actions">
      <button class="btn btn-primary" onclick="openAddProduct()"><i class="fas fa-plus"></i> Add Product</button>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-egg" style="color:var(--maroon)"></i>
    <span class="card-title">Products</span>
    <span id="totalBadge" class="badge badge-active" style="margin-left:8px">0</span>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="productsTable">
      <thead>
        <tr>
          <th>Product</th><th>Category</th><th>Unit</th><th>Buy Price</th><th>Sell Price</th>
          <th>Stock</th><th>Reserved</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody id="productsBody">
        <tr><td colspan="9" style="text-align:center;padding:40px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="productsPagination"></div>
</div>

<!-- Add/Edit Product Modal -->
<div class="modal-overlay" id="productModal">
  <div class="modal-box" style="max-width:600px">
    <div class="modal-header">
      <i class="fas fa-egg" style="color:var(--maroon)"></i>
      <div class="modal-title" id="productModalTitle">Add Product</div>
      <button class="modal-close" onclick="App.closeModal('productModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="productId">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group" style="grid-column:1/-1">
          <label class="form-label">Product Name *</label>
          <input type="text" class="form-control" id="pName" placeholder="e.g. Desi Egg (Tray 30)">
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select class="form-control" id="pCategory">
            <option value="">Select Category</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Unit</label>
          <select class="form-control" id="pUnit">
            <option value="piece">Piece</option>
            <option value="tray">Tray</option>
            <option value="crate">Crate</option>
            <option value="pack">Pack</option>
            <option value="dozen">Dozen</option>
          </select>
        </div>
        <!-- Image Upload -->
        <div class="form-group" style="grid-column:1/-1">
          <label class="form-label">Product Image</label>
          <div id="pImagePreviewContainer" style="margin-bottom:8px;display:none">
            <img id="pImagePreview" src="" alt="Product Image" style="max-width:100%;border-radius:var(--radius-sm)"/>
          </div>
          <input type="file" class="form-control" id="pImage" accept="image/*">
        </div>
        <div class="form-group">
          <label class="form-label">Unit Size (pieces)</label>
          <input type="number" class="form-control" id="pUnitSize" value="1" min="1">
        </div>

        <div class="form-group">
          <label class="form-label">Buying Price (৳) *</label>
          <input type="number" class="form-control" id="pBuyPrice" step="0.01" placeholder="0.00">
        </div>
        <div class="form-group">
          <label class="form-label">Selling Price (৳) *</label>
          <input type="number" class="form-control" id="pSellPrice" step="0.01" placeholder="0.00">
        </div>
        <div class="form-group">
          <label class="form-label">Initial Stock</label>
          <input type="number" class="form-control" id="pStock" value="0" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Low Stock Alert</label>
          <input type="number" class="form-control" id="pLowAlert" value="100" min="0">
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="pDesc" rows="2"></textarea>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="App.closeModal('productModal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveProduct()"><i class="fas fa-save"></i> Save Product</button>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
async function loadCategories() {
  const resp = await App.get('shared/profile.php', { categories: 1 });
  if (!resp?.success) return;
  const cats = resp.data;
  ['categoryFilter', 'pCategory'].forEach(id => {
    const sel = document.getElementById(id);
    if (!sel) return;
    const current = sel.value;
    const options = id === 'categoryFilter' ? '<option value="">All Categories</option>' : '<option value="">Select Category</option>';
    sel.innerHTML = options + cats.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    sel.value = current;
  });
}

async function loadProducts(page = 1) {
  const params = {
    page, search: document.getElementById('searchInput').value,
    category_id: document.getElementById('categoryFilter').value,
    status: document.getElementById('statusFilter').value, page_size: 20,
  };

  const tbody = document.getElementById('productsBody');
  tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('admin/products.php', params);
  if (!resp?.success) return;

  document.getElementById('totalBadge').textContent = resp.pagination.total;

  if (!resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon">📦</div><div class="empty-state-title">No products</div></div></td></tr>';
    return;
  }

  tbody.innerHTML = resp.data.map(p => {
    const available = p.current_stock - p.reserved_stock;
    const lowStock = available <= p.low_stock_alert;
    return `<tr class="fade-in">
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:36px;height:36px;overflow:hidden;border-radius:var(--radius-sm);background:#f0f0f0;display:flex;align-items:center;justify-content:center">
            ${p.image ? `<img src="${p.image}" style="width:100%;height:100%;object-fit:cover"/>` : '🥚'}
          </div>
          <div>
            <div style="font-weight:700">${p.name}</div>
            <div style="font-size:11px;color:var(--text-muted)">Unit: ${p.unit}</div>
          </div>
        </div>
      </td>
      <td>${p.category_name||'-'}</td>
      <td>${p.unit} (×${p.unit_size})</td>
      <td>${App.formatMoney(p.buying_price)}</td>
      <td><b style="color:var(--maroon)">${App.formatMoney(p.selling_price)}</b></td>
      <td><span style="color:${lowStock?'var(--danger)':'var(--success)'};font-weight:700">${p.current_stock}</span></td>
      <td style="color:var(--text-muted)">${p.reserved_stock}</td>
      <td>${App.statusBadge(p.status)}</td>
      <td>
        <div style="display:flex;gap:4px">
          <button class="btn btn-sm btn-ghost" onclick="editProduct(${p.id})"><i class="fas fa-edit"></i></button>
          <button class="btn btn-sm btn-danger" onclick="deleteProduct(${p.id}, '${p.name}')"><i class="fas fa-trash"></i></button>
        </div>
      </td>
    </tr>`;
  }).join('');

  App.renderPagination('productsPagination', resp.pagination.total, page, resp.pagination.page_size, 'loadProducts');
}

function openAddProduct() {
  document.getElementById('productId').value = '';
  document.getElementById('productModalTitle').textContent = 'Add Product';
  ['pName','pDesc'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('pUnit').value = 'piece';
  document.getElementById('pUnitSize').value = 1;
  document.getElementById('pBuyPrice').value = '';
  document.getElementById('pSellPrice').value = '';
  document.getElementById('pStock').value = 0;
  document.getElementById('pLowAlert').value = 100;
  document.getElementById('pCategory').value = '';
  // reset image preview
  const previewContainer = document.getElementById('pImagePreviewContainer');
  const previewImg = document.getElementById('pImagePreview');
  previewImg.src = '';
  previewContainer.style.display = 'none';
  document.getElementById('pImage').value = '';
  App.openModal('productModal');
}

async function editProduct(id) {
  const resp = await App.get(`admin/products.php?id=${id}`);
  if (!resp?.success) return;
  const p = resp.data;
  document.getElementById('productId').value = p.id;
  document.getElementById('productModalTitle').textContent = 'Edit Product';
  document.getElementById('pName').value = p.name;

  document.getElementById('pDesc').value = p.description||'';
  document.getElementById('pUnit').value = p.unit;
  document.getElementById('pUnitSize').value = p.unit_size;
  document.getElementById('pBuyPrice').value = p.buying_price;
  document.getElementById('pSellPrice').value = p.selling_price;
  document.getElementById('pStock').value = p.current_stock;
  document.getElementById('pLowAlert').value = p.low_stock_alert;
  document.getElementById('pCategory').value = p.category_id||'';
  // show existing image if any
  const previewContainer = document.getElementById('pImagePreviewContainer');
  const previewImg = document.getElementById('pImagePreview');
  if (p.image) {
    previewImg.src = p.image;
    previewContainer.style.display = 'block';
  } else {
    previewImg.src = '';
    previewContainer.style.display = 'none';
  }
  document.getElementById('pImage').value = '';
  App.openModal('productModal');
}

async function saveProduct() {
  const id = document.getElementById('productId').value;
  // Build FormData to support file upload
  const formData = new FormData();
  formData.append('name', document.getElementById('pName').value.trim());

  formData.append('description', document.getElementById('pDesc').value.trim());
  formData.append('category_id', document.getElementById('pCategory').value || '');
  formData.append('unit', document.getElementById('pUnit').value);
  formData.append('unit_size', parseInt(document.getElementById('pUnitSize').value));
  formData.append('buying_price', parseFloat(document.getElementById('pBuyPrice').value));
  formData.append('selling_price', parseFloat(document.getElementById('pSellPrice').value));
  formData.append('current_stock', parseInt(document.getElementById('pStock').value));
  formData.append('low_stock_alert', parseInt(document.getElementById('pLowAlert').value));

  const imageFile = document.getElementById('pImage').files[0];
  if (imageFile) formData.append('image', imageFile);

  // Basic validation
  if (!formData.get('name') || !formData.get('buying_price') || !formData.get('selling_price')) {
    App.toast('warning', 'Required', 'Name and prices are required');
    return;
  }

  let resp;
  if (id) {
    // Update uses update_id query param per backend implementation
    resp = await App.upload(`admin/products.php?update_id=${id}`, formData);
  } else {
    resp = await App.upload('admin/products.php', formData);
  }

  if (resp?.success) {
    App.toast('success', id ? 'Updated!' : 'Created!', formData.get('name'));
    App.closeModal('productModal');
    loadProducts();
  } else {
    App.toast('error', 'Failed', resp?.message);
  }
}

async function deleteProduct(id, name) {
  App.confirm('Deactivate Product', `Deactivate "${name}"?`, async () => {
    const resp = await App.delete(`admin/products.php?id=${id}`);
    if (resp?.success) { App.toast('success', 'Done', 'Product deactivated'); loadProducts(); }
    else App.toast('error', 'Failed', resp?.message);
  });
}

loadCategories();
loadProducts();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
