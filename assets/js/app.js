/**
 * LabTrack Pro — Main JavaScript
 */

// ─── Dark Mode ────────────────────────────────────────────
function toggleDark() {
  const html = document.documentElement;
  const isDark = html.getAttribute('data-theme') === 'dark';
  html.setAttribute('data-theme', isDark ? 'light' : 'dark');
  localStorage.setItem('labtrack_theme', isDark ? 'light' : 'dark');
}

(function initTheme() {
  const saved = localStorage.getItem('labtrack_theme');
  if (saved) document.documentElement.setAttribute('data-theme', saved);
})();

// ─── Sidebar Toggle ───────────────────────────────────────
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// Close sidebar on outside click (mobile)
document.addEventListener('click', (e) => {
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('menuToggle');
  if (sidebar && window.innerWidth <= 900 && sidebar.classList.contains('open')) {
    if (!sidebar.contains(e.target) && e.target !== toggle) {
      sidebar.classList.remove('open');
    }
  }
});

// ─── Modal System ─────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// Close on Escape
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => {
      m.classList.remove('open');
      document.body.style.overflow = '';
    });
  }
});

// ─── Confirm Delete ───────────────────────────────────────
function confirmDelete(id, name, url) {
  const modal = document.getElementById('confirmModal');
  if (!modal) return;
  document.getElementById('confirmMsg').textContent = `Delete "${name}"? This cannot be undone.`;
  document.getElementById('confirmBtn').onclick = () => { window.location = url; };
  openModal('confirmModal');
}

// ─── View Item Details ────────────────────────────────────
function viewItem(id) {
  const overlay = document.getElementById('viewModal');
  const body = document.getElementById('viewModalBody');
  if (!overlay || !body) return;
  body.innerHTML = '<div class="spinner"></div>';
  openModal('viewModal');
  fetch(`ajax/get_item.php?id=${id}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) { body.innerHTML = `<p style="color:var(--c-danger)">${data.error}</p>`; return; }
      renderItemDetail(body, data);
    })
    .catch(() => { body.innerHTML = '<p style="color:var(--c-danger)">Failed to load item.</p>'; });
}

function renderItemDetail(container, d) {
  const hazardIcon = {'Flammable':'🔥','Corrosive':'⚗️','Toxic':'☠️','Oxidizer':'🔆','Biohazard':'☣️','Radioactive':'☢️','Explosive':'💥','Irritant':'⚠️','None':'—'};
  const statusMap = {'active':'badge-success','expired':'badge-danger','low_stock':'badge-warning','under_maintenance':'badge-info','discontinued':'badge-secondary'};
  container.innerHTML = `
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px">
      <div>
        <span class="td-code">${d.item_code}</span>
        <h2 style="font-size:1.1rem;margin-top:2px">${escHtml(d.item_name)}</h2>
      </div>
      <span class="badge ${statusMap[d.status]||'badge-secondary'}">${d.status.replace('_',' ')}</span>
    </div>
    <div class="detail-grid" style="margin-bottom:20px">
      ${field('Category', d.category_name)}
      ${field('Quantity', `${d.quantity} ${d.unit}`)}
      ${field('Min. Quantity', `${d.min_quantity} ${d.unit}`)}
      ${field('Storage', d.location_name)}
      ${field('Supplier', d.supplier_name || '—')}
      ${field('Lot Number', d.lot_number || '—')}
      ${field('Expiry Date', d.expiry_date || '—')}
      ${field('Date Received', d.date_received || '—')}
      ${field('Cost/Unit', d.cost_per_unit ? '$'+parseFloat(d.cost_per_unit).toFixed(2) : '—')}
      ${field('Hazard Class', `${hazardIcon[d.hazard_class]||''} ${d.hazard_class}`)}
    </div>
    ${d.notes ? `<div style="margin-bottom:20px"><label style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--c-text-3);display:block;margin-bottom:4px">Notes</label><p style="font-size:.875rem">${escHtml(d.notes)}</p></div>` : ''}
    <div class="qr-wrap">
      <div id="qrcode-${d.id}"></div>
      <span style="font-size:.72rem;color:var(--c-text-3)">Item QR Code: ${d.item_code}</span>
    </div>`;
  // Generate QR
  if (typeof QRCode !== 'undefined') {
    new QRCode(document.getElementById(`qrcode-${d.id}`), {
      text: `LABTRACK|${d.item_code}|${d.item_name}`,
      width: 100, height: 100,
      colorDark: '#0D7C85', colorLight: '#ffffff',
    });
  }
}

function field(label, val) {
  return `<div class="detail-item"><label>${label}</label><div class="val">${escHtml(String(val||'—'))}</div></div>`;
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── Edit Item Load ────────────────────────────────────────
function editItem(id) {
  fetch(`ajax/get_item.php?id=${id}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) { showToast('error', data.error); return; }
      populateEditForm(data);
      openModal('itemModal');
    });
}

function populateEditForm(d) {
  const form = document.getElementById('itemForm');
  if (!form) return;
  form.action = 'inventory.php?action=update';
  document.getElementById('modal-title').textContent = 'Edit Item';
  setVal('edit_id', d.id);
  setVal('item_name', d.item_name);
  setVal('category_id', d.category_id);
  setVal('quantity', d.quantity);
  setVal('min_quantity', d.min_quantity);
  setVal('unit', d.unit);
  setVal('storage_location_id', d.storage_location_id);
  setVal('supplier_id', d.supplier_id || '');
  setVal('lot_number', d.lot_number || '');
  setVal('expiry_date', d.expiry_date || '');
  setVal('date_received', d.date_received || '');
  setVal('cost_per_unit', d.cost_per_unit || '');
  setVal('hazard_class', d.hazard_class);
  setVal('status', d.status);
  setVal('notes', d.notes || '');
}

function setVal(name, val) {
  const el = document.querySelector(`[name="${name}"]`);
  if (el) el.value = val;
}

function openAddModal() {
  const form = document.getElementById('itemForm');
  if (form) {
    form.reset();
    form.action = 'inventory.php?action=add';
    setVal('edit_id', '');
  }
  document.getElementById('modal-title').textContent = 'Add New Item';
  openModal('itemModal');
}

// ─── Toast ────────────────────────────────────────────────
function showToast(type, msg) {
  const existing = document.querySelectorAll('.toast-dynamic');
  existing.forEach(t => t.remove());
  const t = document.createElement('div');
  t.className = `toast toast-${type} toast-dynamic`;
  t.innerHTML = `<span>${escHtml(msg)}</span><button onclick="this.parentElement.remove()">×</button>`;
  document.body.appendChild(t);
  setTimeout(() => t.classList.add('hiding'), 4000);
  setTimeout(() => t.remove(), 4500);
}

// ─── Live Search Debounce ─────────────────────────────────
let searchTimer;
function debouncedSearch(input, delay = 400) {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    const url = new URL(window.location);
    url.searchParams.set('search', input.value);
    url.searchParams.set('page', 1);
    window.location = url.toString();
  }, delay);
}

// ─── Alert Read ────────────────────────────────────────────
function markAlertRead(id, el) {
  fetch(`ajax/mark_alert.php?id=${id}`)
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const row = el.closest('.alert-item');
        if (row) row.style.opacity = '0.4';
      }
    });
}

function markAllRead() {
  fetch('ajax/mark_alert.php?all=1')
    .then(r => r.json())
    .then(() => location.reload());
}

// ─── Stock Movement Form ───────────────────────────────────
function openMovementModal(itemId, itemName) {
  setVal('mov_item_id', itemId);
  const title = document.getElementById('mov-item-name');
  if (title) title.textContent = itemName;
  openModal('movementModal');
}

// ─── CSV Export ───────────────────────────────────────────
function exportCSV() {
  const url = new URL('reports.php', window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/'));
  url.searchParams.set('action', 'export_csv');
  // pass current filter params
  const currentUrl = new URL(window.location);
  currentUrl.searchParams.forEach((v, k) => { if (k !== 'page') url.searchParams.set(k, v); });
  window.location = url.toString();
}

// ─── Chart Helpers ────────────────────────────────────────
window.LabCharts = {
  createStockChart(canvasId, labels, data, colors) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Quantity',
          data,
          backgroundColor: colors || '#0D7C8555',
          borderColor: colors || '#0D7C85',
          borderWidth: 2,
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { color: '#DDE3EA44' } },
          x: { grid: { display: false } }
        }
      }
    });
  },

  createDoughnut(canvasId, labels, data, colors) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
      type: 'doughnut',
      data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 0, hoverOffset: 8 }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'right', labels: { padding: 16, font: { size: 12 } } } },
        cutout: '65%'
      }
    });
  },

  createLineChart(canvasId, labels, datasets) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { font: { size: 12 } } } },
        scales: {
          y: { beginAtZero: true, grid: { color: '#DDE3EA44' } },
          x: { grid: { display: false } }
        },
        elements: { point: { radius: 4 }, line: { tension: 0.35 } }
      }
    });
  }
};
