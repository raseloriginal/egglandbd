// ============================================================
// EGGLAND BD — Global Application JS
// ============================================================

const App = {
  apiBase: '/egglandbd/api',
  token: localStorage.getItem('eg_token'),
  user: (() => {
    try {
      const u = localStorage.getItem('eg_user');
      return u ? JSON.parse(u) : null;
    } catch(e) { return null; }
  })(),

  // ── Init ────────────────────────────────────────────────
  init() {
    this.token = localStorage.getItem('eg_token');
    const userData = localStorage.getItem('eg_user');
    if (userData) this.user = JSON.parse(userData);

    // Guard: if not logged in and not on login page, redirect
    const isLoginPage = window.location.pathname.includes('index.php') || window.location.pathname.endsWith('/egglandbd/') || window.location.pathname.endsWith('/egglandbd');
    if (!this.token && !isLoginPage) {
      window.location.href = '/egglandbd/index.php';
      return;
    }

    this._initSidebar();
    this._initToastContainer();
    this._loadNotificationCount();
    this._initSidebarActiveLinks();

    // Bind notifications toggle
    const notifBtn = document.getElementById('notifBtn');
    if (notifBtn) {
      notifBtn.onclick = () => {
        this.openModal('notifModal');
        this.loadNotificationsList();
      };
    }

    // Start background poller
    this._startNotificationPoller();
  },

  // ── API Request ─────────────────────────────────────────
  async request(endpoint, options = {}) {
    const url = `${this.apiBase}/${endpoint}`;
    const headers = {
      ...(this.token ? { 'Authorization': `Bearer ${this.token}` } : {}),
      ...options.headers,
    };

    if (!(options.body instanceof FormData)) {
      headers['Content-Type'] = headers['Content-Type'] || 'application/json';
    }

    try {
      const resp = await fetch(url, { ...options, headers });
      const data = await resp.json();

      if (resp.status === 401) {
        this.logout();
        return null;
      }

      return data;
    } catch (err) {
      console.error('API Error:', err);
      this.toast('error', 'Network Error', 'Could not connect to server.');
      return null;
    }
  },

  async get(endpoint, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const url = qs ? `${endpoint}?${qs}` : endpoint;
    return this.request(url, { method: 'GET' });
  },

  async post(endpoint, body) {
    return this.request(endpoint, { method: 'POST', body: JSON.stringify(body) });
  },

  async upload(endpoint, formData) {
    return this.request(endpoint, { method: 'POST', body: formData });
  },

  async put(endpoint, body, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const url = qs ? `${endpoint}?${qs}` : endpoint;
    return this.request(url, { method: 'PUT', body: JSON.stringify(body) });
  },

  async patch(endpoint, body) {
    return this.request(endpoint, { method: 'PATCH', body: JSON.stringify(body) });
  },

  async delete(endpoint) {
    return this.request(endpoint, { method: 'DELETE' });
  },

  // ── Auth ─────────────────────────────────────────────────
  async login(username, password) {
    const resp = await this.post('auth/login.php', { username, password });
    if (resp?.success) {
      localStorage.setItem('eg_token', resp.data.token);
      localStorage.setItem('eg_refresh', resp.data.refresh_token);
      localStorage.setItem('eg_user', JSON.stringify(resp.data.user));
      this.token = resp.data.token;
      this.user = resp.data.user;
    }
    return resp;
  },

  logout() {
    localStorage.removeItem('eg_token');
    localStorage.removeItem('eg_refresh');
    localStorage.removeItem('eg_user');
    window.location.href = '/egglandbd/index.php';
  },

  // ── Toast Notifications ──────────────────────────────────
  _initToastContainer() {
    if (!document.getElementById('toastContainer')) {
      const el = document.createElement('div');
      el.id = 'toastContainer';
      el.className = 'toast-container';
      document.body.appendChild(el);
    }
  },

  toast(type = 'success', title = '', message = '', duration = 4000) {
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `
      <i class="fas ${icons[type] || icons.success} toast-icon"></i>
      <div>
        ${title ? `<div class="toast-title">${title}</div>` : ''}
        ${message ? `<div class="toast-msg">${message}</div>` : ''}
      </div>
    `;
    const container = document.getElementById('toastContainer');
    container.appendChild(el);

    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateX(110%)';
      el.style.transition = '0.3s';
      setTimeout(() => el.remove(), 300);
    }, duration);
  },

  // ── Sidebar ──────────────────────────────────────────────
  _initSidebar() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (toggle && sidebar) {
      toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay?.classList.toggle('show');
      });
      overlay?.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
      });
    }

    // Populate user info in sidebar
    if (this.user) {
      const nameEl = document.getElementById('sidebarUserName');
      const roleEl = document.getElementById('sidebarUserRole');
      const avatarEl = document.getElementById('sidebarAvatar');
      if (nameEl) nameEl.textContent = this.user.name;
      if (roleEl) roleEl.textContent = this.user.role_name;
      if (avatarEl) avatarEl.textContent = this.user.name.charAt(0).toUpperCase();
    }
  },

  _initSidebarActiveLinks() {
    const current = window.location.pathname;
    document.querySelectorAll('.sidebar-link').forEach(link => {
      if (link.getAttribute('href') && current.endsWith(link.getAttribute('href').split('/').pop())) {
        link.classList.add('active');
      }
    });
  },

  // ── Notifications ────────────────────────────────────────
  async _loadNotificationCount() {
    if (!this.token) return;
    const resp = await this.get('shared/notifications.php', { count: 1 });
    if (resp?.success) {
      const badge = document.getElementById('notifCount');
      if (badge) {
        if (resp.data.unread > 0) {
          badge.textContent = resp.data.unread;
          badge.style.display = 'block';
        } else {
          badge.style.display = 'none';
        }
      }
    }
  },

  _startNotificationPoller() {
    if (!this.token) return;

    if (Notification.permission === 'default') {
      Notification.requestPermission();
    }

    let lastUnreadCount = 0;

    // Check every 45s for updates
    setInterval(async () => {
      const resp = await this.get('shared/notifications.php', { count: 1 });
      if (resp?.success) {
        const unread = parseInt(resp.data.unread || 0);
        const badge = document.getElementById('notifCount');
        if (badge) {
          if (unread > 0) {
            badge.textContent = unread;
            badge.style.display = 'block';
          } else {
            badge.style.display = 'none';
          }
        }

        if (unread > lastUnreadCount) {
          const listResp = await this.get('shared/notifications.php', { page: 1 });
          if (listResp?.success && listResp.data.length > 0) {
            const newest = listResp.data[0];
            if (newest.is_read == 0) {
              this.showBrowserNotification(newest.title, newest.message);
              this.toast('info', newest.title, newest.message);
            }
          }
        }
        lastUnreadCount = unread;
      }
    }, 45000);
  },

  showBrowserNotification(title, message) {
    if (Notification.permission === 'granted') {
      new Notification(title, {
        body: message,
        icon: '/egglandbd/assets/images/logo.png'
      });
    }
  },

  async loadNotificationsList() {
    const listEl = document.getElementById('notifList');
    if (!listEl) return;

    listEl.innerHTML = '<div style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></div>';

    const resp = await this.get('shared/notifications.php');
    if (!resp?.success || !resp.data.length) {
      listEl.innerHTML = `
        <div style="text-align:center;padding:40px;color:var(--text-muted)">
          <i class="far fa-bell-slash" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.5"></i>
          No notifications yet.
        </div>
      `;
      return;
    }

    listEl.innerHTML = resp.data.map(n => `
      <div style="padding:14px;border-bottom:1px solid var(--border-light);cursor:pointer;background:${n.is_read == 0 ? 'var(--maroon-50)' : 'white'};display:flex;align-items:start;gap:10px" onclick="App.markNotificationRead(${n.id})">
        <div style="background:var(--maroon);color:white;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0">
          <i class="fas ${n.type === 'order' ? 'fa-shopping-cart' : n.type === 'delivery' ? 'fa-truck' : 'fa-bell'}"></i>
        </div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:13px;color:var(--text-dark);display:flex;justify-content:space-between;align-items:center">
            ${n.title}
            ${n.is_read == 0 ? '<span style="width:8px;height:8px;border-radius:50%;background:var(--maroon);display:inline-block"></span>' : ''}
          </div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:2px">${n.message}</div>
          <div style="font-size:10px;color:var(--text-muted);margin-top:4px">${this.formatDateTime(n.created_at)}</div>
        </div>
      </div>
    `).join('');
  },

  async markNotificationRead(id) {
    const resp = await this.put('shared/notifications.php', { id });
    if (resp?.success) {
      this.loadNotificationsList();
      this._loadNotificationCount();
    }
  },

  async markAllNotificationsRead() {
    const resp = await this.put('shared/notifications.php', { mark_all: true });
    if (resp?.success) {
      this.loadNotificationsList();
      this._loadNotificationCount();
      this.toast('success', 'Success', 'All notifications marked as read');
    }
  },

  // ── Modal Helpers ────────────────────────────────────────
  openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('show');
  },

  closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('show');
  },

  // ── Bottom Sheet Helpers ─────────────────────────────────
  openSheet(id) {
    const overlay = document.getElementById(id + 'Overlay');
    const sheet = document.getElementById(id);
    overlay?.classList.add('show');
    sheet?.classList.add('show');
  },

  closeSheet(id) {
    const overlay = document.getElementById(id + 'Overlay');
    const sheet = document.getElementById(id);
    overlay?.classList.remove('show');
    sheet?.classList.remove('show');
  },

  // ── Format Helpers ───────────────────────────────────────
  formatMoney(amount) {
    return '৳' + parseFloat(amount || 0).toLocaleString('en-BD', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  },

  formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-BD', { day: '2-digit', month: 'short', year: 'numeric' });
  },

  formatDateTime(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('en-BD', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  },

  statusBadge(status) {
    return `<span class="badge badge-${status?.toLowerCase()}">${status || '-'}</span>`;
  },

  // ── Table Rendering ──────────────────────────────────────
  renderTable(tableId, rows, columns) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!tbody) return;
    if (!rows || rows.length === 0) {
      tbody.innerHTML = `<tr><td colspan="${columns.length}" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:8px;opacity:0.4"></i>No records found</td></tr>`;
      return;
    }
    tbody.innerHTML = rows.map(row => {
      return `<tr class="fade-in">${columns.map(col => {
        let val = row[col.field] ?? '-';
        if (col.render) val = col.render(val, row);
        return `<td>${val}</td>`;
      }).join('')}</tr>`;
    }).join('');
  },

  renderPagination(containerId, total, page, pageSize, onPageChange) {
    const totalPages = Math.ceil(total / pageSize);
    const el = document.getElementById(containerId);
    if (!el) return;

    let html = `<span style="font-size:12px;color:var(--text-muted);margin-right:auto">Showing ${Math.min((page-1)*pageSize+1,total)}–${Math.min(page*pageSize,total)} of ${total}</span>`;
    html += `<button class="page-btn" ${page<=1?'disabled':''} onclick="${onPageChange}(${page-1})"><i class="fas fa-chevron-left"></i></button>`;
    
    let start = Math.max(1, page - 2);
    let end = Math.min(totalPages, start + 4);
    if (end - start < 4) start = Math.max(1, end - 4);
    
    for (let i = start; i <= end; i++) {
      html += `<button class="page-btn ${i===page?'active':''}" onclick="${onPageChange}(${i})">${i}</button>`;
    }
    html += `<button class="page-btn" ${page>=totalPages?'disabled':''} onclick="${onPageChange}(${page+1})"><i class="fas fa-chevron-right"></i></button>`;
    el.innerHTML = html;
  },

  // ── Confirm Dialog ───────────────────────────────────────
  confirm(title, message, onConfirm) {
    const overlay = document.getElementById('confirmOverlay');
    const titleEl = document.getElementById('confirmTitle');
    const msgEl = document.getElementById('confirmMsg');
    const btn = document.getElementById('confirmBtn');

    if (!overlay) return;
    titleEl.textContent = title;
    msgEl.textContent = message;
    overlay.classList.add('show');
    btn.onclick = () => {
      overlay.classList.remove('show');
      onConfirm();
    };
  },

  // ── Loading State ────────────────────────────────────────
  setLoading(containerId, loading = true) {
    const el = document.getElementById(containerId);
    if (!el) return;
    if (loading) {
      el.innerHTML = `<div class="loader"><div class="spinner"></div> Loading...</div>`;
    }
  },
};

// ── Global Helpers ────────────────────────────────────────
const $ = id => document.getElementById(id);
const $$ = sel => document.querySelectorAll(sel);

function debounce(fn, ms = 300) {
  let timer;
  return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
}

// ── Auto-init ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => App.init());

// ── PWA Service Worker Registration ───────────────────────
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/egglandbd/sw.js')
      .then(reg => console.log('[PWA] SW registered:', reg.scope))
      .catch(err => console.warn('[PWA] SW failed:', err));
  });
}

