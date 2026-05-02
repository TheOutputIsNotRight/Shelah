// ============================================
// Shelah — Auth State Management
// ============================================

window.currentUser = null;

async function checkAuth() {
  try {
    const data = await getMe();
    window.currentUser = data.user;
    updateAuthUI();
    return data.user;
  } catch (e) {
    window.currentUser = null;
    updateAuthUI();
    return null;
  }
}

function requireAuth() {
  if (!window.currentUser) {
    window.location.href = '/index.html?login=1';
    return false;
  }
  return true;
}

function updateAuthUI() {
  const user = window.currentUser;
  // Nav user area
  const navUser = document.getElementById('nav-user');
  if (navUser) {
    if (user) {
      const initial = (user.display_name || '?')[0].toUpperCase();
      navUser.innerHTML = `<div class="nav-user-name">
        <div class="nav-avatar">${escapeHtml(initial)}</div>
        <span>${escapeHtml(user.display_name)}</span>
      </div>
      <button class="nav-signout" onclick="handleLogout()">Sign Out</button>`;
    } else {
      navUser.innerHTML = `<button class="btn btn-accent btn-sm" onclick="openAuthModal()">Sign In</button>`;
    }
  }
  // Toggle auth-dependent elements
  document.querySelectorAll('[data-auth="required"]').forEach(el => {
    el.style.display = user ? '' : 'none';
  });
  document.querySelectorAll('[data-auth="guest"]').forEach(el => {
    el.style.display = user ? 'none' : '';
  });
}

async function handleLogout() {
  try {
    await logout();
    window.currentUser = null;
    updateAuthUI();
    showToast('Signed out successfully', 'info');
    if (window.location.pathname !== '/' && !window.location.pathname.includes('index.html')) {
      window.location.href = '/index.html';
    }
  } catch (e) {
    showToast('Logout failed', 'error');
  }
}

// --- Toast System ---
function showToast(message, type = 'info') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}

// --- Modal System ---
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}
function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}
function closeAllModals() {
  document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
  document.body.style.overflow = '';
}

// Close modals on backdrop click or Escape
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
    document.body.style.overflow = '';
  }
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeAllModals();
});

// --- Utility ---
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr + 'T00:00:00');
  return new Intl.DateTimeFormat('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }).format(d);
}

function getAvatarColor(name) {
  const colors = [
    'linear-gradient(135deg,#FF1D58,#F75990)',
    'linear-gradient(135deg,#0049B7,#00DDFF)',
    'linear-gradient(135deg,#ffe033,#ffaa00)',
    'linear-gradient(135deg,#00b4d8,#0049B7)',
    'linear-gradient(135deg,#10B981,#00b09b)',
    'linear-gradient(135deg,#F75990,#ff8cb8)',
    'linear-gradient(135deg,#7c3aed,#a78bfa)',
    'linear-gradient(135deg,#ffe033,#FF1D58)'
  ];
  let hash = 0;
  for (let i = 0; i < (name||'').length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
  return colors[Math.abs(hash) % colors.length];
}

function avatarHtml(name, size = 40) {
  const c = getAvatarColor(name);
  const initial = (name || '?')[0].toUpperCase();
  return `<div class="avatar" style="width:${size}px;height:${size}px;background:${c};font-size:${size*0.4}px;border:2px solid white;box-shadow:0 2px 8px rgba(0,0,0,.12)">${initial}</div>`;
}

function starsHtml(rating) {
  const r = parseFloat(rating) || 0;
  let s = '';
  for (let i = 1; i <= 5; i++) {
    s += i <= Math.round(r) ? '★' : '☆';
  }
  return `<span class="place-stars">${s}</span> <span>${r.toFixed(1)}</span>`;
}

function budgetEmoji(tier) {
  const map = { budget: '💰', moderate: '💰💰', upscale: '💰💰💰', luxury: '💰💰💰💰' };
  return map[tier] || '💰';
}

function haversineDistance(lat1, lon1, lat2, lon2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// --- Button Loading ---
function btnLoading(btn, loading) {
  if (loading) {
    btn.disabled = true;
    btn.dataset.origText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span> Loading...';
  } else {
    btn.disabled = false;
    btn.innerHTML = btn.dataset.origText || btn.innerHTML;
  }
}

// --- Init auth on every page ---
document.addEventListener('DOMContentLoaded', () => {
  checkAuth().then(() => {
    if (typeof onAuthReady === 'function') onAuthReady();
  });
});
