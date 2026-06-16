import './bootstrap';

/* ── Yardımcılar ────────────────────────────────────────────── */

const qs = (selector, scope = document) => scope.querySelector(selector);

const escapeHtml = (value) =>
    String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';

const adminConfig = {
    apiBase:            meta('admin-api-base') || '/api',
    token:              meta('admin-api-token'),
    role:               meta('admin-user-role'),
    canManageStructure: meta('admin-can-manage-structure') === '1',
    canManageShops:     meta('admin-can-manage-shops') === '1',
    canManageStudios:   meta('admin-can-manage-studios') === '1',
    isAdmin:            meta('admin-is-admin') === '1',
    isStudioAdmin:      meta('admin-is-studio-admin') === '1',
    isSupervisor:       meta('admin-is-supervisor') === '1',
    canManageUsers:     meta('admin-can-manage-users') === '1',
};

const firebaseWebConfig = {
    apiKey:            meta('firebase-web-api-key'),
    authDomain:        meta('firebase-web-auth-domain'),
    projectId:         meta('firebase-web-project-id'),
    storageBucket:     meta('firebase-web-storage-bucket'),
    messagingSenderId: meta('firebase-web-sender-id'),
    appId:             meta('firebase-web-app-id'),
    measurementId:     meta('firebase-web-measurement-id'),
};

const firebaseWebVapidKey = meta('firebase-web-vapid-key');

/* ── Sabit çeviriler ────────────────────────────────────────── */

const STATUS_LABELS = {
    completed:   'Tamamlandı',
    confirmed:   'Onaylandı',
    pending:     'Bekliyor',
    cancelled:   'İptal',
    rescheduled: 'Yeniden Planlandı',
    working:     'Çalışıyor',
    break:       'Mola',
    transfer:    'Transfer',
    active:      'Aktif',
};

const ROLE_LABELS = {
    admin:         'Platform Admin',
    yonetici:      'Yönetici',
    supervisor:    'Süpervizör',
    designer:      'Tasarımcı',
    artist:        'Artist',
    info:          'Info',
    sofor:         'Şoför',
    calisan:       'Çalışan',
    kullanici_rol: 'Kullanıcı (Rol)',
    kullanici:     'Kullanıcı',
};

const APPOINTMENT_TYPE_LABELS = {
    designer: 'Tasarım',
    tattoo:   'Dövme',
};

const statusLabel = (s) => STATUS_LABELS[s] ?? s;
const roleLabel   = (r) => ROLE_LABELS[r]   ?? r;
const uniqueById  = (items = []) => {
    const seen = new Set();
    return items.filter((item) => {
        if (!item?.id || seen.has(String(item.id))) return false;
        seen.add(String(item.id));
        return true;
    });
};
const usesStudioAssignment = (role) => ['artist', 'designer'].includes(role);
const isRegularUserRole = () => ['kullanici', 'kullanici_rol'].includes(adminConfig.role);
const isArtistLikeRole = () => ['artist', 'designer', 'kullanici_rol'].includes(adminConfig.role);
const isDriverRole = () => adminConfig.role === 'sofor';
const canManageAppointmentRecords = () =>
    ['admin', 'yonetici', 'supervisor', 'info', 'calisan'].includes(adminConfig.role);

const requestStatusLabel = (status) => ({
    pending:  'Bekliyor',
    accepted: 'Kabul Edildi',
    rejected: 'Reddedildi',
}[status] || status);

const requestStatusClass = (status) => ({
    pending:  'badge-pill badge-pill--warning',
    accepted: 'badge-pill badge-pill--success',
    rejected: 'badge-pill badge-pill--danger',
}[status] || 'badge-pill');

/* ── Toast ──────────────────────────────────────────────────── */

const toastRoot = () => qs('#admin-toast-root');

const showToast = (message, type = 'info') => {
    const root = toastRoot();
    if (!root) return;

    const icons = {
        success: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#86EFB0;flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>',
        error:   '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#FCA5A5;flex-shrink:0"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        info:    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#BAD7FE;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    };

    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.innerHTML = `
        <div style="display:flex;align-items:flex-start;gap:0.6rem">
            ${icons[type] || icons.info}
            <div>
                <div style="font-size:0.8rem;font-weight:600;color:var(--text-main)">${type === 'error' ? 'İşlem Başarısız' : type === 'success' ? 'Başarılı' : 'Bilgi'}</div>
                <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-muted)">${escapeHtml(message)}</div>
            </div>
        </div>
    `;
    root.appendChild(toast);

    window.setTimeout(() => {
        toast.style.transition = 'opacity 200ms ease, transform 200ms ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(6px)';
        window.setTimeout(() => toast.remove(), 220);
    }, 3600);
};

/* ── API yardımcısı ─────────────────────────────────────────── */

const apiFetch = async (path, options = {}) => {
    const url     = `${adminConfig.apiBase}${path}`;
    const headers = new Headers(options.headers || {});

    headers.set('Accept', 'application/json');
    if (adminConfig.token) headers.set('Authorization', `Bearer ${adminConfig.token}`);

    const isFormData = options.body instanceof FormData;
    if (!isFormData && options.body && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(url, {
        ...options,
        headers,
        body: !options.body || isFormData || typeof options.body === 'string'
            ? options.body
            : JSON.stringify(options.body),
    });

    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : { message: await response.text() };

    if (!response.ok) {
        const errorMessage =
            payload?.message ||
            payload?.error ||
            Object.values(payload?.errors || {})?.flat?.()?.[0] ||
            'Beklenmeyen bir hata oluştu.';
        throw new Error(errorMessage);
    }

    return payload;
};

/* ── Tarih formatları ───────────────────────────────────────── */

const formatDate = (value) => {
    if (!value) return '—';
    return new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
};

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Intl.DateTimeFormat('tr-TR', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    }).format(new Date(value));
};

/* ── Durum badge CSS ────────────────────────────────────────── */

const statusClass = (status) => ({
    completed:   'badge-pill badge-pill--success',
    confirmed:   'badge-pill badge-pill--info',
    pending:     'badge-pill badge-pill--warning',
    cancelled:   'badge-pill badge-pill--danger',
    rescheduled: 'badge-pill badge-pill--warning',
    working:     'badge-pill badge-pill--success',
    break:       'badge-pill badge-pill--warning',
    transfer:    'badge-pill badge-pill--info',
    active:      'badge-pill badge-pill--success',
}[status] || 'badge-pill');

const roleBadgeClass = (role) => ({
    admin:      'badge-pill--danger',
    yonetici:   'badge-pill--warning',
    supervisor: 'badge-pill--info',
    designer:   'badge-pill--teal',
    artist:     'badge-pill--success',
    sofor:      'badge-pill--warning',
}[role] || '');

/* ── Yardımcı render ────────────────────────────────────────── */

const skeletonGrid = (count = 4) =>
    `<div style="display:grid;gap:0.75rem">${Array.from({ length: count }, () => '<div class="skeleton" style="height:4.5rem"></div>').join('')}</div>`;

const animateCounters = (scope = document) => {
    scope.querySelectorAll('[data-counter]').forEach((node) => {
        const target   = Number(node.getAttribute('data-counter') || '0');
        const duration = 650;
        const startTime = performance.now();

        const tick = (time) => {
            const progress = Math.min((time - startTime) / duration, 1);
            const eased    = 1 - Math.pow(1 - progress, 3);
            node.textContent = Math.round(target * eased).toLocaleString('tr-TR');
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    });
};

const handleAsync = async (fn, fallbackMessage = 'İşlem tamamlanamadı.') => {
    try {
        await fn();
    } catch (error) {
        showToast(error.message || fallbackMessage, 'error');
    }
};

/* ── Paylaşılan bileşenler ──────────────────────────────────── */

const pageHeader = (eyebrow, title, desc, badgeHtml = '') => `
    <div class="hero-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.5rem">${eyebrow}</div>
                <h1 class="page-hero-title">${title}</h1>
                ${desc ? `<p class="page-hero-desc" style="margin-top:0.5rem">${desc}</p>` : ''}
            </div>
            ${badgeHtml ? `<div style="align-self:flex-start">${badgeHtml}</div>` : ''}
        </div>
    </div>
`;

const metricCard = (label, value, helper, accentColor = '', delay = '') => `
    <article class="metric-card${delay ? ` animate-stagger-${delay}` : ''}">
        <div class="section-eyebrow"${accentColor ? ` style="color:${accentColor}"` : ''}>${label}</div>
        <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;letter-spacing:-0.025em;color:var(--text-main)" data-counter="${value}">0</div>
        <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-subtle)">${helper}</div>
    </article>
`;

const statBlock = (label, content) => `
    <div class="stat-block">
        <div class="stat-label">${label}</div>
        <div style="margin-top:0.4rem;font-weight:600;font-size:0.845rem;color:var(--text-main)">${content}</div>
    </div>
`;

const renderDiscoveryHome = (root, data = {}) => {
    const studios = data.studios || [];
    root.innerHTML = `
        ${pageHeader('Keşfet', 'Stüdyolar', 'Randevu talebi gönderebileceğiniz aktif stüdyolar.', '<span class="badge-pill badge-pill--teal">Kullanıcı Paneli</span>')}
        <div class="data-grid">
            ${studios.map((studio, i) => `
                <article class="data-card animate-stagger-${(i % 3) + 1}">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;margin-bottom:0.85rem">
                        <div>
                            <div class="section-title">${escapeHtml(studio.name)}</div>
                            <div style="margin-top:0.25rem;font-size:0.75rem;color:var(--text-muted)">${escapeHtml(studio.location || studio.shop?.location || 'Konum yok')}</div>
                        </div>
                        <span class="badge-pill" style="font-size:0.62rem">${escapeHtml(studio.shop?.company?.name || studio.shop?.name || 'Stüdyo')}</span>
                    </div>
                    <div style="font-size:0.78rem;color:var(--text-muted);line-height:1.55;min-height:2.4rem">${escapeHtml(studio.about || 'Bu stüdyo için henüz açıklama eklenmemiş.')}</div>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.65rem;margin-top:1rem;padding-top:0.85rem;border-top:1px solid var(--border)">
                        <span style="font-size:0.72rem;color:var(--text-subtle)">${escapeHtml(studio.opening_time || '09:00')} - ${escapeHtml(studio.closing_time || '18:00')}</span>
                        <a href="/admin/appointment-requests?studio_id=${encodeURIComponent(studio.id)}" class="button-secondary" style="padding:0.45rem 0.75rem;font-size:0.74rem">Talep Gönder</a>
                    </div>
                </article>
            `).join('') || '<div class="empty-state">Gösterilecek stüdyo bulunamadı.</div>'}
        </div>
    `;
};

/* ── Dashboard ──────────────────────────────────────────────── */

const renderDashboard = async (root, selectedStudioId = '') => {
    root.innerHTML = `
        ${pageHeader('Genel Bakış', 'Operasyon Özeti', 'Canlı metrikler, dönemsel raporlar ve sahadaki hareketler tek akışta.', '<span class="badge-pill badge-pill--info">Canlı</span>')}
        <div class="panel-card" style="padding:0.85rem 1rem">
            <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
                <div>
                    <div class="section-eyebrow" style="margin-bottom:0.2rem">İstatistik Kapsamı</div>
                    <div style="font-size:0.78rem;color:var(--text-muted)">Genel toplamı veya tek bir stüdyoyu görüntüleyin.</div>
                </div>
                <select data-dashboard-studio-filter style="margin-left:auto;min-width:220px">
                    <option value="">Genel istatistikler</option>
                </select>
            </div>
        </div>
        ${adminConfig.isAdmin ? `<div class="panel-card" data-dashboard-companies>${skeletonGrid(2)}</div>` : ''}
        <div class="metric-grid" data-dashboard-metrics>${skeletonGrid(4)}</div>
        <div class="data-grid" data-dashboard-reports>${skeletonGrid(3)}</div>
        <div class="panel-card" data-dashboard-staff-reports>${skeletonGrid(3)}</div>
        <div style="display:grid;gap:1rem;grid-template-columns:1.1fr 0.9fr">
            <div class="panel-card" data-dashboard-studios>${skeletonGrid(1)}</div>
            <div class="panel-card" data-dashboard-appointments>${skeletonGrid(1)}</div>
        </div>
    `;

    const studioPayload = await apiFetch('/studios/options').catch(() => ({ data: [] }));
    const studioOptions = studioPayload.data || [];
    const studioSelect = qs('[data-dashboard-studio-filter]', root);
    studioSelect.innerHTML = `
        <option value="">Genel istatistikler</option>
        ${studioOptions.map((studio) => `<option value="${studio.id}">${escapeHtml(studio.name)}</option>`).join('')}
    `;
    studioSelect.value = selectedStudioId;
    studioSelect.addEventListener('change', () => renderDashboard(root, studioSelect.value));

    const payload = await apiFetch(`/home${selectedStudioId ? `?studio_id=${encodeURIComponent(selectedStudioId)}` : ''}`);
    if (payload.type === 'discovery') {
        renderDiscoveryHome(root, payload.data || {});
        return;
    }
    const data    = payload.data;

    qs('[data-dashboard-metrics]', root).innerHTML = [
        ['Toplam Randevu',  data.summary.total_appointments,    'Tüm dönem',       '',                '1'],
        ['İptal',           data.summary.cancelled_appointments,'Risk takibi',     'var(--danger)',   '2'],
        ['Aktif Ekip',      data.summary.active_staff_count,    'Canlı personel',  'var(--success)',  '3'],
        ['Transfer',        data.summary.transfer_count,        'Sürücü görevleri','var(--info)',     '1'],
    ].map(([label, value, helper, color, delay]) => metricCard(label, value, helper, color, delay)).join('');

    qs('[data-dashboard-reports]', root).innerHTML = Object.values(data.reports || {}).map((report, i) => `
        <article class="data-card animate-stagger-${(i % 3) + 1}">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem">
                <div>
                    <div class="section-title">${escapeHtml(report.label)}</div>
                    <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">${escapeHtml(report.date_from)} — ${escapeHtml(report.date_to)}</div>
                </div>
                <span class="badge-pill">Dönem Raporu</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem">
                ${[
                    ['Toplam',     report.total_appointments,     ''],
                    ['Tamamlandı', report.completed_appointments, 'var(--success)'],
                    ['İptal',      report.cancelled_appointments,  'var(--danger)'],
                    ['Aktif',      report.confirmed_appointments ?? report.active_appointments ?? 0, 'var(--warning)'],
                ].map(([lbl, val, color]) => `
                    <div class="stat-block">
                        <div class="stat-label">${lbl}</div>
                        <div style="margin-top:0.4rem;font-size:1.5rem;font-weight:800;letter-spacing:-0.02em;color:${color || 'var(--text-main)'}" data-counter="${val}">0</div>
                    </div>
                `).join('')}
            </div>
        </article>
    `).join('');

    const staffReports = data.staff_reports || [];
    qs('[data-dashboard-staff-reports]', root).innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.3rem">Personel</div>
                <div class="section-title">Ekip Bazlı Raporlar</div>
            </div>
            <span class="badge-pill">${staffReports.length} personel</span>
        </div>
        <div class="data-grid">
            ${staffReports.map((staff, i) => {
                const stats  = staff.stats || {};
                const weekly = staff.weekly_data || [];
                const maxVal = Math.max(1, ...weekly.map((d) => Number(d.value || 0)));
                return `
                    <article class="data-card animate-stagger-${(i % 3) + 1}" style="padding:1.1rem 1.25rem">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;margin-bottom:1rem">
                            <div style="display:flex;align-items:center;gap:0.6rem">
                                <div style="width:2rem;height:2rem;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-lo));display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;color:#0C1220;flex-shrink:0">
                                    ${escapeHtml((staff.name || '?').charAt(0).toUpperCase())}
                                </div>
                                <div>
                                    <div style="font-size:0.845rem;font-weight:600;color:var(--text-main)">${escapeHtml(staff.name)}</div>
                                    <div style="font-size:0.72rem;color:var(--text-muted)">${escapeHtml(staff.role || 'Personel')}</div>
                                </div>
                            </div>
                            <span class="badge-pill" style="font-size:0.62rem">${escapeHtml((staff.studio_names || []).join(', ') || 'Stüdyo')}</span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem;margin-bottom:1rem">
                            ${[
                                ['Toplam', stats.total_appointments || 0],
                                ['Tamam',  stats.completed || 0],
                                ['İptal',  stats.cancelled || 0],
                                ['Hafta',  stats.this_week || 0],
                            ].map(([lbl, val]) => `
                                <div class="stat-block" style="padding:0.55rem 0.65rem">
                                    <div class="stat-label" style="font-size:0.58rem">${lbl}</div>
                                    <div style="margin-top:0.3rem;font-size:1.1rem;font-weight:700;color:var(--text-main)" data-counter="${val}">0</div>
                                </div>
                            `).join('')}
                        </div>
                        <div style="display:flex;align-items:flex-end;gap:3px;height:72px">
                            ${weekly.map((day) => {
                                const val = Number(day.value || 0);
                                const h   = Math.max(8, Math.round((val / maxVal) * 62));
                                return `
                                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                                        <div title="${escapeHtml(day.day)}: ${val}" style="width:100%;height:${h}px;border-radius:3px 3px 2px 2px;background:linear-gradient(180deg,var(--accent),var(--accent-lo));opacity:${val > 0 ? 0.85 : 0.2}"></div>
                                        <div style="font-size:0.58rem;color:var(--text-subtle)">${escapeHtml(day.day)}</div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </article>
                `;
            }).join('') || '<div class="empty-state" style="padding:1.5rem;border:none">Bu kapsamda personel raporu bulunmuyor.</div>'}
        </div>
    `;

    qs('[data-dashboard-studios]', root).innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.3rem">Stüdyo</div>
                <div class="section-title">Stüdyo Performansı</div>
            </div>
            <span class="badge-pill">${data.studios.length} lokasyon</span>
        </div>
        <div class="table-shell">
            <table>
                <thead>
                    <tr>
                        <th>Stüdyo Adı</th>
                        <th>Konum</th>
                        <th>Aktif Ekip</th>
                        <th>Randevu</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.studios.map((studio) => `
                        <tr>
                            <td style="font-weight:600">${escapeHtml(studio.name)}</td>
                            <td style="color:var(--text-muted)">${escapeHtml(studio.location || '—')}</td>
                            <td><span class="badge-pill badge-pill--success" style="font-size:0.65rem">${studio.active_staff_count}</span></td>
                            <td style="font-weight:600">${studio.appointments_count}</td>
                        </tr>
                    `).join('') || '<tr><td colspan="4" style="color:var(--text-muted);text-align:center;padding:1.5rem">Stüdyo bulunamadı.</td></tr>'}
                </tbody>
            </table>
        </div>
    `;

    qs('[data-dashboard-appointments]', root).innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.3rem">Günlük</div>
                <div class="section-title">Bugünün Randevuları</div>
            </div>
            <span class="badge-pill badge-pill--warning">${data.today_appointments.length} kayıt</span>
        </div>
        <div class="list-stack">
            ${data.today_appointments.map((apt) => `
                <div class="list-card" style="padding:0.7rem 0.85rem">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem">
                        <div>
                            <div style="font-weight:600;font-size:0.845rem;color:var(--text-main)">${escapeHtml(`${apt.customer.first_name} ${apt.customer.last_name}`)}</div>
                            <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">${escapeHtml(apt.customer.hotel_name || apt.studio || '—')}</div>
                            <div style="margin-top:0.2rem;font-size:0.7rem;color:var(--text-subtle)">${formatDateTime(apt.appointment_at)}</div>
                        </div>
                        <span class="${statusClass(apt.status)}" style="font-size:0.65rem;flex-shrink:0">${statusLabel(apt.status)}</span>
                    </div>
                </div>
            `).join('') || '<div class="empty-state" style="padding:1.5rem;border:none">Bugün için randevu bulunmuyor.</div>'}
        </div>
    `;

    if (adminConfig.isAdmin) {
        const compPayload = await apiFetch('/companies').catch(() => ({ data: [] }));
        const companies   = compPayload.data || [];
        qs('[data-dashboard-companies]', root).innerHTML = `
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
                <div>
                    <div class="section-eyebrow" style="margin-bottom:0.3rem">Platform</div>
                    <div class="section-title">Şirket Randevu Hacimleri</div>
                </div>
                <span class="badge-pill">${companies.length} şirket</span>
            </div>
            <div class="table-shell">
                <table>
                    <thead>
                        <tr>
                            <th>Şirket Adı</th>
                            <th>Dükkan</th>
                            <th>Stüdyo</th>
                            <th>Randevu</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${companies.map((company) => `
                            <tr>
                                <td style="font-weight:600">${escapeHtml(company.name)}</td>
                                <td style="color:var(--text-muted)">${company.shop_count} / ${company.max_shop_count === 0 ? '∞' : company.max_shop_count}</td>
                                <td style="color:var(--text-muted)">${company.studio_count} / ${company.max_studio_count === 0 ? '∞' : company.max_studio_count}</td>
                                <td style="font-weight:700" data-counter="${company.appointment_count}">0</td>
                            </tr>
                        `).join('') || '<tr><td colspan="4" style="color:var(--text-muted);text-align:center;padding:1.5rem">Şirket bulunamadı.</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    }

    animateCounters(root);
};

/* ── Kullanıcılar ───────────────────────────────────────────── */

const renderUsersPage = async (root) => {
    const roles = adminConfig.isAdmin
        ? ['admin', 'yonetici', 'supervisor', 'designer', 'artist', 'info', 'sofor', 'calisan']
        : adminConfig.canManageShops
        ? ['supervisor', 'designer', 'artist', 'info', 'sofor', 'calisan']
        : ['designer', 'artist', 'info', 'sofor', 'calisan'];

    const canEditRole = (role) => {
        if (adminConfig.isAdmin) return true;
        if (adminConfig.canManageShops) return !['admin', 'yonetici'].includes(role);
        return !['admin', 'yonetici', 'supervisor'].includes(role);
    };

    root.innerHTML = `
        ${pageHeader('Ekip Yönetimi', 'Personel & Kullanıcılar', 'Personel listesi, roller ve stüdyo atamaları tek panelde.', '<span class="badge-pill badge-pill--purple">Ekip</span>')}
        <div style="display:grid;gap:1rem;grid-template-columns:1fr 1fr">
            <div class="panel-card">
                <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem">
                    <div class="field-wrap" style="flex:1;min-width:0">
                        <label class="field-label">Stüdyo Seç</label>
                        <select class="field-select" data-users-studio-select></select>
                    </div>
                    <button class="button-secondary" data-users-refresh style="padding:0.55rem 0.85rem;font-size:0.78rem;flex-shrink:0">Yenile</button>
                </div>
                <div class="list-stack" data-users-list>${skeletonGrid(4)}</div>
            </div>
            ${adminConfig.canManageUsers ? `
            <div class="form-shell" style="align-self:start">
                <div class="section-eyebrow" style="margin-bottom:0.4rem">Personel Ekle</div>
                <div class="section-title" style="margin-bottom:0.3rem">Kullanıcı Oluştur / Ata</div>
                <p style="font-size:0.75rem;color:var(--text-muted);margin-bottom:1.25rem" data-users-assignment-help>Kayıtlı e-posta girilirse mevcut kullanıcı atanır.</p>
                <form class="form-grid" data-users-create-form>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">İsim</label><input class="field-input" name="name" required></div>
                        <div class="field-wrap"><label class="field-label">Soyad</label><input class="field-input" name="surname" required></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="phone" required></div>
                        <div class="field-wrap"><label class="field-label">E-posta</label><input class="field-input" name="email" type="email" required></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap">
                            <label class="field-label">Rol</label>
                            <select class="field-select" name="role" data-users-role-select></select>
                        </div>
                        <div class="field-wrap" data-users-create-studio-wrap>
                            <label class="field-label">Stüdyo</label>
                            <select class="field-select" name="studio_id" data-users-create-studio></select>
                        </div>
                        <div class="field-wrap" data-users-create-shop-wrap style="display:none">
                            <label class="field-label">Şube</label>
                            <select class="field-select" name="shop_id" data-users-create-shop></select>
                        </div>
                    </div>
                    <div class="form-grid form-grid--split" data-users-password-wrap>
                        <div class="field-wrap"><label class="field-label">Şifre <span style="color:var(--text-subtle)">(şube rolleri için zorunlu)</span></label><input class="field-input" name="password" type="password"></div>
                        <div class="field-wrap"><label class="field-label">Şifre Tekrar</label><input class="field-input" name="password_confirmation" type="password"></div>
                    </div>
                    <button class="button-primary" type="submit" style="justify-content:center;margin-top:0.25rem">Kullanıcı Oluştur / Ata</button>
                </form>
            </div>
            ` : `
            <div class="form-shell" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;align-self:start">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-subtle);margin-bottom:0.75rem">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                </svg>
                <div class="section-title">Personel Listesi</div>
                <p style="margin-top:0.4rem;font-size:0.8rem;color:var(--text-muted)">Stüdyo yöneticisi veya üstü rol gerekli.</p>
            </div>
            `}
        </div>
    `;

    const studioSelect       = qs('[data-users-studio-select]', root);
    const createStudioSelect = qs('[data-users-create-studio]', root);
    const createStudioWrap   = qs('[data-users-create-studio-wrap]', root);
    const createShopSelect   = qs('[data-users-create-shop]', root);
    const createShopWrap     = qs('[data-users-create-shop-wrap]', root);
    const passwordWrap       = qs('[data-users-password-wrap]', root);
    const assignmentHelp     = qs('[data-users-assignment-help]', root);
    const listNode           = qs('[data-users-list]', root);
    const form               = qs('[data-users-create-form]', root);
    const roleSelect         = qs('[data-users-role-select]', root);

    if (roleSelect) {
        roleSelect.innerHTML = roles.map((role) =>
            `<option value="${role}">${roleLabel(role)}</option>`
        ).join('');
    }

    const loadStudios = async () => {
        const payload  = await apiFetch('/studios/options');
        const studios  = uniqueById(payload.data || []);
        const options  = studios.map((s) => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        studioSelect.innerHTML = options;
        if (createStudioSelect) createStudioSelect.innerHTML = options;
        return studios;
    };

    const loadShops = async () => {
        if (!createShopSelect) return [];
        const payload = await apiFetch('/shops');
        const shops = uniqueById(payload.data || []);
        createShopSelect.innerHTML = shops.map((s) => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        return shops;
    };

    const updateCreateAssignmentMode = () => {
        if (!roleSelect || !createStudioWrap || !createShopWrap) return;
        const studioMode = usesStudioAssignment(roleSelect.value);
        createStudioWrap.style.display = studioMode ? '' : 'none';
        createShopWrap.style.display = studioMode ? 'none' : '';
        if (passwordWrap) passwordWrap.style.display = studioMode ? 'none' : '';
        if (assignmentHelp) {
            assignmentHelp.textContent = studioMode
                ? 'Artist ve tasarımcılar stüdyoya davet edilir; şifre gerekmez.'
                : 'Supervisor, şoför, info ve çalışan rolleri şubeye atanır.';
        }
    };

    const renderUsers = async () => {
        if (!studioSelect.value) {
            listNode.innerHTML = '<div class="empty-state">Önce bir stüdyo seçin.</div>';
            return;
        }
        listNode.innerHTML = skeletonGrid(4);
        const payload = await apiFetch(`/studios/${studioSelect.value}/users`);
        const users   = payload.data || [];

        listNode.innerHTML = users.length
            ? users.map((user, i) => {
                const canEdit = adminConfig.canManageUsers && canEditRole(user.role);
                return `
                <article class="list-card animate-stagger-${(i % 3) + 1}" data-user-card data-user-id="${user.id}" style="${!user.is_active ? 'opacity:0.55' : ''}">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem">
                        <div style="display:flex;align-items:center;gap:0.65rem;min-width:0">
                            <div style="width:1.85rem;height:1.85rem;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-lo));display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;color:#0C1220;flex-shrink:0">
                                ${escapeHtml((user.name || '?').charAt(0).toUpperCase())}
                            </div>
                            <div style="min-width:0">
                                <div style="font-size:0.845rem;font-weight:600;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(user.name)}</div>
                                <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.1rem">${escapeHtml(user.email)}</div>
                                ${user.phone ? `<div style="font-size:0.7rem;color:var(--text-subtle)">${escapeHtml(user.phone)}</div>` : ''}
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.35rem;flex-shrink:0">
                            <span class="badge-pill ${roleBadgeClass(user.role)}" style="font-size:0.62rem">${roleLabel(user.role)}</span>
                            ${!user.is_active ? '<span class="badge-pill badge-pill--danger" style="font-size:0.58rem">Banlı</span>' : ''}
                        </div>
                    </div>
                    ${canEdit ? `
                    <div style="margin-top:0.85rem;padding-top:0.85rem;border-top:1px solid var(--border)">
                        <div class="form-grid form-grid--split" style="gap:0.6rem;margin-bottom:0.65rem">
                            <div class="field-wrap">
                                <label class="field-label">Rol</label>
                                <select class="field-select" data-user-role style="font-size:0.78rem;padding:0.45rem 0.65rem">
                                    ${roles.map((r) => `<option value="${r}" ${user.role === r ? 'selected' : ''}>${roleLabel(r)}</option>`).join('')}
                                </select>
                            </div>
                            <div class="field-wrap">
                                <label class="field-label">Durum</label>
                                <select class="field-select" data-user-status style="font-size:0.78rem;padding:0.45rem 0.65rem">
                                    ${['working', 'break', 'transfer'].map((s) => `<option value="${s}" ${user.status === s ? 'selected' : ''}>${statusLabel(s)}</option>`).join('')}
                                </select>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem">
                            <label style="display:inline-flex;align-items:center;gap:0.5rem;font-size:0.75rem;color:var(--text-muted);cursor:pointer">
                                <input type="checkbox" data-user-active ${user.is_active ? 'checked' : ''} style="accent-color:var(--accent)">
                                <span>${user.is_active ? 'Aktif' : 'Banlı / Pasif'}</span>
                            </label>
                            <button class="button-primary" data-user-save style="padding:0.4rem 0.85rem;font-size:0.75rem">Kaydet</button>
                        </div>
                    </div>
                    ` : adminConfig.canManageUsers ? `
                    <div style="margin-top:0.7rem;font-size:0.72rem;color:var(--text-subtle)">Bu rolü yalnızca daha üst yetkili hesaplar yönetebilir.</div>
                    ` : ''}
                </article>
            `;
            }).join('')
            : '<div class="empty-state">Bu stüdyoda kullanıcı bulunmuyor.</div>';

        listNode.querySelectorAll('[data-user-save]').forEach((btn) => {
            btn.addEventListener('click', () => handleAsync(async () => {
                const card   = btn.closest('[data-user-card]');
                const userId = card?.getAttribute('data-user-id');
                await apiFetch(`/studios/${studioSelect.value}/users/${userId}`, {
                    method: 'PATCH',
                    body: {
                        role:      qs('[data-user-role]',   card)?.value,
                        status:    qs('[data-user-status]', card)?.value,
                        is_active: qs('[data-user-active]', card)?.checked,
                    },
                });
                showToast('Kullanıcı güncellendi.', 'success');
                await renderUsers();
            }));
        });
    };

    await Promise.all([loadStudios(), loadShops()]);
    updateCreateAssignmentMode();
    await renderUsers();

    studioSelect.addEventListener('change', () => handleAsync(renderUsers));
    roleSelect?.addEventListener('change', updateCreateAssignmentMode);
    qs('[data-users-refresh]', root)?.addEventListener('click', () => handleAsync(renderUsers));

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            handleAsync(async () => {
                const data = Object.fromEntries(new FormData(form).entries());
                if (usesStudioAssignment(data.role)) {
                    delete data.shop_id;
                    delete data.password;
                    delete data.password_confirmation;
                } else {
                    delete data.studio_id;
                    if (!data.password) throw new Error('Şube rolleri için şifre zorunludur.');
                    data.password_confirmation = data.password_confirmation || data.password;
                }
                await apiFetch('/users', { method: 'POST', body: data });
                form.reset();
                if (createStudioSelect) createStudioSelect.value = studioSelect.value;
                showToast('Kullanıcı oluşturuldu / atandı.', 'success');
                await renderUsers();
            });
        });
    }
};

/* ── Randevular ─────────────────────────────────────────────── */

const renderAppointmentsPage = async (root) => {
    const title = isDriverRole() ? 'Transferler' : isArtistLikeRole() ? 'Atanan Randevular' : 'Randevu Yönetimi';
    const desc = isDriverRole()
        ? 'Pick up seçili transferler, müşteri bilgileri ve sürücü aksiyonları.'
        : isArtistLikeRole()
            ? 'Size atanan tasarım ve dövme randevuları.'
            : 'Liste ve durum güncellemeleri tek merkezde.';

    root.innerHTML = `
        ${pageHeader('Randevu Akışı', title, desc, '<span class="badge-pill badge-pill--warning">Canlı Akış</span>')}
        <div style="display:grid;gap:1rem">
            <div class="panel-card">
                <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem">
                    ${canManageAppointmentRecords() ? `
                    <div class="field-wrap" style="flex:1;min-width:0">
                        <label class="field-label">Stüdyo Seç</label>
                        <select class="field-select" data-appointments-studio-select></select>
                    </div>
                    ` : '<div></div>'}
                    <button class="button-secondary" data-appointments-refresh style="padding:0.55rem 0.85rem;font-size:0.78rem;flex-shrink:0">Yenile</button>
                </div>
                <div class="list-stack" data-appointments-list>${skeletonGrid(4)}</div>
            </div>
        </div>
    `;

    const studioSelect = qs('[data-appointments-studio-select]', root);
    const listNode = qs('[data-appointments-list]', root);

    const loadStudios = async () => {
        if (!studioSelect) return;
        const payload = await apiFetch('/studios/options');
        const studios = uniqueById(payload.data || []);
        studioSelect.innerHTML = studios.map((s) => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
    };

    const endpointForRole = () => {
        if (isDriverRole()) return '/my-appointments';
        if (isArtistLikeRole()) return '/my-artist-appointments';
        return studioSelect?.value ? `/studios/${studioSelect.value}/appointments` : null;
    };

    const renderImageThumb = (apt) => {
        const image = apt.customer?.photo_path || apt.photo_path || apt.source_image_path || apt.tattoo_image_paths?.[0];
        return image
            ? `<a href="${escapeHtml(image)}" target="_blank" rel="noopener noreferrer" style="flex-shrink:0"><img src="${escapeHtml(image)}" alt="Randevu görseli" style="width:58px;height:58px;object-fit:cover;border-radius:0.65rem;border:1px solid var(--border)"></a>`
            : '<div style="width:58px;height:58px;border-radius:0.65rem;border:1px solid var(--border);background:var(--surface-soft);flex-shrink:0"></div>';
    };

    const renderAppointments = async () => {
        const endpoint = endpointForRole();
        if (!endpoint) {
            listNode.innerHTML = '<div class="empty-state">Randevuları görüntülemek için bir stüdyo seçin.</div>';
            return;
        }

        listNode.innerHTML = skeletonGrid(4);
        const payload = await apiFetch(endpoint);
        const appointments = payload.data || [];

        listNode.innerHTML = appointments.length
            ? appointments.map((apt, i) => {
                const customerName = `${apt.customer?.first_name || ''} ${apt.customer?.last_name || ''}`.trim();
                const phone = `${apt.customer?.phone_country_code || ''}${apt.customer?.phone_number || ''}`.replace(/\s+/g, '');
                const studioId = apt.studio?.id || studioSelect?.value || '';
                const limited = apt.artist_limited_view;
                return `
                <article class="list-card animate-stagger-${(i % 3) + 1}" data-appointment-id="${apt.id}" data-studio-id="${studioId}" style="padding:0.85rem 1rem">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;margin-bottom:0.85rem">
                        <div style="display:flex;align-items:flex-start;gap:0.75rem;min-width:0">
                            ${renderImageThumb(apt)}
                            <div>
                                <div style="font-size:0.875rem;font-weight:600;color:var(--text-main)">${escapeHtml(limited ? 'Atanan dövme işi' : (customerName || 'İsimsiz'))}</div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">Otel/Yer: ${escapeHtml(limited ? (apt.studio?.name || 'Stüdyo') : (apt.customer?.hotel_name || apt.place || '—'))}</div>
                                <div style="margin-top:0.15rem;font-size:0.7rem;color:var(--text-subtle)">Oda: ${escapeHtml(limited ? '—' : (apt.customer?.room_number || '—'))}</div>
                                <div style="margin-top:0.15rem;font-size:0.7rem;color:var(--text-subtle)">Tarih: ${formatDateTime(apt.appointment_at)}</div>
                                <div style="margin-top:0.15rem;font-size:0.7rem;color:var(--text-subtle)">Stüdyo: ${escapeHtml(apt.studio?.name || 'Bağımsız')}</div>
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.3rem;flex-shrink:0">
                            <span class="${statusClass(apt.status)}" style="font-size:0.65rem">${statusLabel(apt.status)}</span>
                            ${apt.appointment_type ? `<span class="badge-pill badge-pill--teal" style="font-size:0.6rem">${APPOINTMENT_TYPE_LABELS[apt.appointment_type] ?? apt.appointment_type}</span>` : ''}
                            ${apt.price !== null && apt.price !== undefined ? `<span class="badge-pill" style="font-size:0.6rem">${escapeHtml(apt.price)} ₺</span>` : ''}
                        </div>
                    </div>
                    ${canManageAppointmentRecords() ? `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.75rem">
                        <div class="field-wrap">
                            <label class="field-label">Durum</label>
                            <select class="field-select" data-appointment-status style="font-size:0.78rem;padding:0.42rem 0.65rem">
                                ${['confirmed','completed','cancelled'].map((s) => `<option value="${s}" ${apt.status === s ? 'selected' : ''}>${statusLabel(s)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="field-wrap">
                            <label class="field-label">Tip</label>
                            <select class="field-select" data-appointment-type style="font-size:0.78rem;padding:0.42rem 0.65rem">
                                ${['designer','tattoo'].map((t) => `<option value="${t}" ${apt.appointment_type === t ? 'selected' : ''}>${APPOINTMENT_TYPE_LABELS[t]}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                    ` : ''}
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
                        <a href="/admin/appointments/${apt.id}" class="button-ghost" style="padding:0.4rem 0.75rem;font-size:0.75rem">Detay</a>
                        ${canManageAppointmentRecords() ? `
                            <label style="display:flex;align-items:center;gap:0.35rem;font-size:0.72rem;color:var(--text-muted)"><input type="checkbox" data-appointment-pickup ${apt.pickup_required ? 'checked' : ''}> Pick up</label>
                            <button class="button-secondary" data-appointment-save style="padding:0.4rem 0.75rem;font-size:0.75rem">Kaydet</button>
                        ` : ''}
                        ${isDriverRole() ? `
                            ${phone ? `<a href="tel:${escapeHtml(phone)}" class="button-secondary" style="padding:0.4rem 0.75rem;font-size:0.75rem">Müşteriyi Ara</a>` : ''}
                            <button class="button-secondary" data-driver-action="picked_up" style="padding:0.4rem 0.75rem;font-size:0.75rem">Aldım</button>
                            <button class="button-primary" data-driver-action="dropped_off" style="padding:0.4rem 0.75rem;font-size:0.75rem">Bıraktım</button>
                            <button class="button-ghost" data-driver-action="customer_no_show" style="padding:0.4rem 0.75rem;font-size:0.75rem">Müşteri Gelmedi</button>
                        ` : ''}
                        ${adminConfig.role === 'artist' && apt.appointment_type === 'tattoo' && !['completed', 'cancelled'].includes(apt.status) ? `
                            <form data-artist-complete-form style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
                                <input class="field-input" type="file" name="completed_tattoo_image" accept="image/*" required style="max-width:240px;font-size:0.75rem;padding:0.45rem 0.65rem">
                                <button class="button-primary" type="submit" style="padding:0.4rem 0.75rem;font-size:0.75rem">Tamamla</button>
                            </form>
                        ` : ''}
                    </div>
                </article>
            `}).join('')
            : '<div class="empty-state">Bu kapsamda randevu bulunmuyor.</div>';

        listNode.querySelectorAll('[data-appointment-save]').forEach((btn) => {
            btn.addEventListener('click', () => handleAsync(async () => {
                const card = btn.closest('[data-appointment-id]');
                const id = card?.getAttribute('data-appointment-id');
                const studioId = card?.getAttribute('data-studio-id');
                await apiFetch(`/studios/${studioId}/appointments/${id}`, {
                    method: 'PATCH',
                    body: {
                        status: qs('[data-appointment-status]', card)?.value,
                        appointment_type: qs('[data-appointment-type]', card)?.value || 'designer',
                        pickup_required: qs('[data-appointment-pickup]', card)?.checked || false,
                    },
                });
                showToast('Randevu güncellendi.', 'success');
                await renderAppointments();
            }));
        });

        listNode.querySelectorAll('[data-driver-action]').forEach((btn) => {
            btn.addEventListener('click', () => handleAsync(async () => {
                const card = btn.closest('[data-appointment-id]');
                await apiFetch(`/studios/${card?.getAttribute('data-studio-id')}/appointments/${card?.getAttribute('data-appointment-id')}/driver-action`, {
                    method: 'PATCH',
                    body: { driver_status: btn.getAttribute('data-driver-action') },
                });
                showToast('Transfer güncellendi.', 'success');
                await renderAppointments();
            }));
        });

        listNode.querySelectorAll('[data-artist-complete-form]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                handleAsync(async () => {
                    const card = form.closest('[data-appointment-id]');
                    await apiFetch(`/appointments/${card?.getAttribute('data-appointment-id')}/artist-complete`, {
                        method: 'POST',
                        body: new FormData(form),
                    });
                    showToast('Randevu tamamlandı.', 'success');
                    await renderAppointments();
                });
            });
        });
    };

    await loadStudios();
    await renderAppointments();

    studioSelect?.addEventListener('change', () => handleAsync(renderAppointments));
    qs('[data-appointments-refresh]', root)?.addEventListener('click', () => handleAsync(renderAppointments));
};

/* ── Stüdyolar ──────────────────────────────────────────────── */

const renderStudiosPage = async (root) => {
    const isStudioAdminOnly = adminConfig.canManageStudios && !adminConfig.canManageShops;

    root.innerHTML = `
        ${pageHeader(
            'Stüdyo Yönetimi',
            isStudioAdminOnly ? 'Stüdyonuzu Yönetin' : 'Stüdyo Ağı',
            isStudioAdminOnly
                ? 'Logo, isim, konum ve bildirim ayarlarını bu ekrandan yapılandırın.'
                : 'Lokasyon, ekip yoğunluğu ve yapılandırma bilgileri tek kartta.',
            '<span class="badge-pill badge-pill--purple">Stüdyo Ayarları</span>'
        )}
        <div style="display:grid;gap:1rem;grid-template-columns:1.1fr 0.9fr">
            <div class="data-grid" data-studios-grid>${skeletonGrid(isStudioAdminOnly ? 1 : 3)}</div>
            ${adminConfig.canManageStudios ? `
            <div class="form-shell" data-studio-create-shell style="align-self:start">
                <div class="section-eyebrow" style="margin-bottom:0.4rem">Yeni Stüdyo</div>
                <div class="section-title" style="margin-bottom:1.25rem">Stüdyo Oluştur</div>
                <form class="form-grid" data-studio-create-form>
                    <div class="field-wrap">
                        <label class="field-label">Bağlı Şube</label>
                        <select class="field-select" name="shop_id" data-studio-create-shop required></select>
                    </div>
                    <div class="field-wrap"><label class="field-label">Stüdyo Adı</label><input class="field-input" name="name" required></div>
                    <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location"></div>
                    <button class="button-primary" type="submit" style="justify-content:center">Stüdyo Oluştur</button>
                </form>
            </div>
            ` : ''}
        </div>
    `;

    const grid    = qs('[data-studios-grid]', root);
    const [payload, shopsPayload] = await Promise.all([
        apiFetch('/studios/overview'),
        adminConfig.canManageStudios ? apiFetch('/shops') : Promise.resolve({ data: [] }),
    ]);
    const studios = payload.data || [];
    const shops   = uniqueById(shopsPayload.data || []);
    const createShopSelect = qs('[data-studio-create-shop]', root);
    if (createShopSelect) {
        createShopSelect.innerHTML = shops.length
            ? shops.map((shop) => `<option value="${shop.id}">${escapeHtml(shop.name)}</option>`).join('')
            : '<option value="">Önce şube oluşturun</option>';
        createShopSelect.disabled = shops.length === 0;
    }

    grid.innerHTML = studios.length
        ? studios.map((studio, i) => `
            <article class="data-card animate-stagger-${(i % 3) + 1}">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;margin-bottom:1rem">
                    <div>
                        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.10em;color:var(--text-muted);margin-bottom:0.3rem">${escapeHtml(studio.shop?.name || 'Dükkan bilgisi yok')}</div>
                        <div class="section-title">${escapeHtml(studio.name)}</div>
                    </div>
                    <span class="badge-pill badge-pill--info" style="font-size:0.65rem;flex-shrink:0">${studio.appointments_count} randevu</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;margin-bottom:1.25rem">
                    ${statBlock('Konum', escapeHtml(studio.location || '—'))}
                    ${statBlock('Aktif Ekip', String(studio.active_staff_count))}
                </div>
                <div style="padding-top:1.1rem;border-top:1px solid var(--border)">
                    <form class="form-grid" data-studio-form data-studio-id="${studio.id}">
                        <div class="form-grid form-grid--split">
                            <div class="field-wrap"><label class="field-label">Stüdyo Adı</label><input class="field-input" name="name" value="${escapeHtml(studio.name)}"></div>
                            <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location" value="${escapeHtml(studio.location || '')}"></div>
                        </div>
                        <div class="field-wrap"><label class="field-label">Logo URL</label><input class="field-input" name="logo_path" value="${escapeHtml(studio.logo_path || '')}" placeholder="https://..."></div>
                        <button class="button-primary" type="submit" style="justify-content:center">Ayarları Kaydet</button>
                    </form>
                </div>
            </article>
        `).join('')
        : '<div class="empty-state">Erişilebilir stüdyo bulunmuyor.</div>';

    grid.querySelectorAll('[data-studio-form]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            handleAsync(async () => {
                const data = Object.fromEntries(new FormData(form).entries());
                await apiFetch(`/studios/${form.getAttribute('data-studio-id')}`, { method: 'PATCH', body: data });
                showToast('Stüdyo kaydı güncellendi.', 'success');
                await renderStudiosPage(root);
            });
        });
    });

    qs('[data-studio-create-form]', root)?.addEventListener('submit', (e) => {
        e.preventDefault();
        handleAsync(async () => {
            const form = e.target;
            const data = Object.fromEntries(new FormData(form).entries());
            if (!data.shop_id) throw new Error('Stüdyo oluşturmak için önce şube seçin.');
            await apiFetch('/studios', { method: 'POST', body: data });
            showToast('Stüdyo oluşturuldu.', 'success');
            await renderStudiosPage(root);
        });
    });
};

/* ── Dükkanlar ──────────────────────────────────────────────── */

const renderShopsPage = async (root) => {
    root.innerHTML = `
        ${pageHeader('Şube Yönetimi', 'Şube Ağı', 'Şube kartları, supervisor eşleştirmesi ve yapılandırma aynı yerde.', '<span class="badge-pill badge-pill--success">Şube Yönetimi</span>')}
        <div style="display:grid;gap:1rem;grid-template-columns:1.1fr 0.9fr">
            <div class="panel-card" data-shops-list>${skeletonGrid(3)}</div>
            <div class="form-shell" data-shops-create style="align-self:start"></div>
        </div>
    `;

    const listNode   = qs('[data-shops-list]',   root);
    const createNode = qs('[data-shops-create]',  root);

    const [shopsPayload, companiesPayload] = await Promise.all([
        apiFetch('/shops'),
        adminConfig.canManageShops ? apiFetch('/companies') : Promise.resolve({ data: [] }),
    ]);

    const shops      = shopsPayload.data    || [];
    const companies  = companiesPayload.data || [];
    const companyMap = Object.fromEntries(companies.map((c) => [String(c.id), c.name]));

    const uniqueCompanyIds = [...new Set(shops.map((s) => s.company_id).filter(Boolean))];
    const supervisorsByCompany = {};
    if (adminConfig.canManageShops && uniqueCompanyIds.length) {
        const results = await Promise.all(
            uniqueCompanyIds.map((cid) =>
                apiFetch(`/users/options?roles=supervisor&company_id=${cid}`)
                    .then((p) => [String(cid), p.data || []])
            )
        );
        results.forEach(([cid, users]) => { supervisorsByCompany[cid] = users; });
    }
    if (adminConfig.canManageShops) {
        supervisorsByCompany.__all = await apiFetch('/users/options?roles=supervisor')
            .then((p) => uniqueById(p.data || []))
            .catch(() => []);
    }

    const buildSupervisorOptions = (companyId, selectedId = null) => {
        const list = uniqueById(supervisorsByCompany[String(companyId)] || supervisorsByCompany.__all || []);
        return `<option value="">Supervisor seçin (opsiyonel)</option>${list.map((m) =>
            `<option value="${m.id}" ${String(m.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(m.name)} — ${roleLabel(m.role)}</option>`
        ).join('')}`;
    };

    const buildCompanyOptions = (selectedId = null) =>
        `<option value="">Şirket seçin</option>${companies.map((c) =>
            `<option value="${c.id}" ${String(c.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(c.name)}</option>`
        ).join('')}`;

    listNode.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.3rem">Dükkan Ağı</div>
                <div class="section-title">Aktif Şubeler</div>
            </div>
            <span class="badge-pill">${shops.length} şube</span>
        </div>
        <div class="list-stack">
            ${shops.map((shop, i) => `
                <article class="list-card animate-stagger-${(i % 3) + 1}" style="padding:0.9rem 1rem">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;margin-bottom:0.85rem">
                        <div>
                            <div style="font-size:0.875rem;font-weight:600;color:var(--text-main)">${escapeHtml(shop.name)}</div>
                            <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">${escapeHtml(shop.location || '—')}${companyMap[String(shop.company_id)] ? ` · ${escapeHtml(companyMap[String(shop.company_id)])}` : ''}</div>
                        </div>
                        <span class="${shop.is_active ? 'badge-pill badge-pill--success' : 'badge-pill badge-pill--danger'}" style="font-size:0.62rem;flex-shrink:0">
                            ${shop.is_active ? 'Aktif' : 'Pasif'}
                        </span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:1rem">
                        ${statBlock('Supervisor', escapeHtml(shop.supervisor?.name || shop.manager?.name || '—'))}
                        ${statBlock('Bağlı Stüdyolar', shop.studios.map((s) => escapeHtml(s.name)).join(', ') || '—')}
                    </div>
                    <div style="padding-top:0.85rem;border-top:1px solid var(--border)">
                        <form class="form-grid" data-shop-form data-shop-id="${shop.id}" style="gap:0.6rem">
                            <div class="field-wrap"><label class="field-label">Şube Adı</label><input class="field-input" name="name" value="${escapeHtml(shop.name)}"></div>
                            <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location" value="${escapeHtml(shop.location || '')}"></div>
                            ${adminConfig.canManageShops ? `
                                <div class="field-wrap">
                                    <label class="field-label">Supervisor</label>
                                    <select class="field-select" name="supervisor_user_id">
                                        ${buildSupervisorOptions(shop.company_id, shop.supervisor?.id ?? shop.manager?.id ?? null)}
                                    </select>
                                </div>
                            ` : ''}
                            <button class="button-primary" type="submit" style="justify-content:center;padding:0.5rem">Kaydet</button>
                        </form>
                    </div>
                </article>
            `).join('') || '<div class="empty-state">Dükkan bulunamadı.</div>'}
        </div>
    `;

    createNode.innerHTML = adminConfig.canManageShops
        ? `
            <div class="section-eyebrow" style="margin-bottom:0.4rem">Yeni Lokasyon</div>
            <div class="section-title" style="margin-bottom:1.25rem">Yeni Şube Oluştur</div>
            <form class="form-grid" data-shop-create-form>
                ${adminConfig.isAdmin ? `
                <div class="field-wrap">
                    <label class="field-label">Şirket</label>
                    <select class="field-select" name="company_id" required data-company-select>${buildCompanyOptions()}</select>
                </div>
                ` : ''}
                <div class="field-wrap"><label class="field-label">Şube Adı</label><input class="field-input" name="name" required></div>
                <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location"></div>
                <div class="field-wrap">
                    <label class="field-label">Supervisor <span style="color:var(--text-subtle)">(opsiyonel)</span></label>
                    <select class="field-select" name="supervisor_user_id" data-supervisor-select>
                        ${adminConfig.isAdmin ? '<option value="">Önce şirket seçin</option>' : buildSupervisorOptions('__all')}
                    </select>
                </div>
                <button class="button-primary" type="submit" style="justify-content:center">Şube Oluştur</button>
            </form>
        `
        : `
            <div class="empty-state">
                <div class="section-title">Dükkan bilgileri senkronize</div>
                <p style="margin-top:0.4rem;font-size:0.8rem;color:var(--text-muted)">Size ait dükkan kartları listelenir.</p>
            </div>
        `;

    const createForm = qs('[data-shop-create-form]', root);
    if (createForm) {
        const companySelect = qs('[data-company-select]', createForm);
        const supervisorSelect = qs('[data-supervisor-select]', createForm);

        companySelect?.addEventListener('change', async () => {
            const cid = companySelect.value;
            if (!cid) { supervisorSelect.innerHTML = '<option value="">Önce şirket seçin</option>'; return; }
            supervisorSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            supervisorSelect.disabled = true;
            try {
                const { data } = await apiFetch(`/users/options?roles=supervisor&company_id=${cid}`);
                supervisorsByCompany[String(cid)] = data || [];
                supervisorSelect.innerHTML = buildSupervisorOptions(cid);
            } finally {
                supervisorSelect.disabled = false;
            }
        });

        createForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleAsync(async () => {
                const body = Object.fromEntries(new FormData(createForm).entries());
                if (!body.supervisor_user_id) delete body.supervisor_user_id;
                if (!adminConfig.isAdmin) delete body.company_id;
                await apiFetch('/shops', { method: 'POST', body });
                showToast('Yeni şube eklendi.', 'success');
                await renderShopsPage(root);
            });
        });
    }

    listNode.querySelectorAll('[data-shop-form]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            handleAsync(async () => {
                const body = Object.fromEntries(new FormData(form).entries());
                if (!body.supervisor_user_id) delete body.supervisor_user_id;
                await apiFetch(`/shops/${form.getAttribute('data-shop-id')}`, { method: 'PATCH', body });
                showToast('Dükkan güncellendi.', 'success');
                await renderShopsPage(root);
            });
        });
    });
};

/* ── Şirketler ──────────────────────────────────────────────── */

const renderCompaniesPage = async (root) => {
    const managerPayload = await apiFetch('/users/options?roles=yonetici');
    const managers = uniqueById(managerPayload.data || []);
    const buildManagerOptions = (selectedId = null) =>
        `<option value="">Yönetici seçin (opsiyonel)</option>${managers.map((manager) =>
            `<option value="${manager.id}" ${String(manager.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(manager.name)}</option>`
        ).join('')}`;

    root.innerHTML = `
        ${pageHeader('Şirket Yönetimi', 'Şirket Ağı', 'Şirket bazlı dükkan, stüdyo ve randevu verilerini anlık görün.', '<span class="badge-pill badge-pill--info">Platform Yönetimi</span>')}
        <div style="display:grid;gap:1rem;grid-template-columns:1.1fr 0.9fr">
            <div class="panel-card" data-companies-list>${skeletonGrid(3)}</div>
            <div class="form-shell" style="align-self:start">
                <div class="section-eyebrow" style="margin-bottom:0.4rem">Yeni Şirket</div>
                <div class="section-title" style="margin-bottom:1.25rem">Şirket Oluştur</div>
                <form class="form-grid" data-company-create-form>
                    <div class="field-wrap"><label class="field-label">Şirket Adı</label><input class="field-input" name="name" required></div>
                    <div class="field-wrap"><label class="field-label">Adres</label><input class="field-input" name="address"></div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="phone"></div>
                        <div class="field-wrap"><label class="field-label">E-posta</label><input class="field-input" name="email" type="email"></div>
                    </div>
                    <input type="hidden" name="create_manager" value="1">
                    <div style="padding:0.8rem;border:1px solid var(--border);border-radius:14px;background:var(--surface-soft)">
                        <div class="section-eyebrow" style="margin-bottom:0.65rem">Şirket Yönetici Hesabı</div>
                        <div class="form-grid form-grid--split">
                            <div class="field-wrap"><label class="field-label">Yönetici Adı</label><input class="field-input" name="manager_name" required></div>
                            <div class="field-wrap"><label class="field-label">Yönetici Soyadı</label><input class="field-input" name="manager_surname"></div>
                        </div>
                        <div class="form-grid form-grid--split">
                            <div class="field-wrap"><label class="field-label">Yönetici E-posta</label><input class="field-input" name="manager_email" type="email" required></div>
                            <div class="field-wrap"><label class="field-label">Yönetici Telefon</label><input class="field-input" name="manager_phone"></div>
                        </div>
                        <div class="field-wrap"><label class="field-label">Yönetici Şifre</label><input class="field-input" name="manager_password" type="password" required minlength="6"></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Max Dükkan <span style="color:var(--text-subtle)">(0=∞)</span></label><input class="field-input" type="number" min="0" name="max_shop_count" value="0"></div>
                        <div class="field-wrap"><label class="field-label">Max Stüdyo <span style="color:var(--text-subtle)">(0=∞)</span></label><input class="field-input" type="number" min="0" name="max_studio_count" value="0"></div>
                    </div>
                    <button class="button-primary" type="submit" style="justify-content:center">Şirket Oluştur</button>
                </form>
            </div>
        </div>
    `;

    const listNode = qs('[data-companies-list]', root);

    const limitBadge = (current, max) => {
        const text = max === 0 ? `${current} / ∞` : `${current} / ${max}`;
        const cls  = max > 0 && current >= max ? 'danger' : 'success';
        return `<span class="badge-pill badge-pill--${cls}" style="font-size:0.65rem">${text}</span>`;
    };

    const renderCompanies = async () => {
        listNode.innerHTML = skeletonGrid(3);
        const payload   = await apiFetch('/companies');
        const companies = payload.data || [];

        listNode.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
                <div>
                    <div class="section-eyebrow" style="margin-bottom:0.3rem">Şirket Ağı</div>
                    <div class="section-title">Kayıtlı Şirketler</div>
                </div>
                <span class="badge-pill">${companies.length} şirket</span>
            </div>
            <div class="list-stack">
                ${companies.length ? companies.map((company, i) => `
                    <article class="list-card animate-stagger-${(i % 3) + 1}" style="padding:0.9rem 1rem">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;margin-bottom:0.85rem">
                            <div>
                                <div style="font-size:0.875rem;font-weight:600;color:var(--text-main)">${escapeHtml(company.name)}</div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">${escapeHtml(company.address || '—')}</div>
                            </div>
                            <span class="badge-pill badge-pill--info" style="font-size:0.62rem;flex-shrink:0">${company.appointment_count} randevu</span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;margin-bottom:1rem">
                            <div class="stat-block">
                                <div class="stat-label">Randevu</div>
                                <div style="margin-top:0.4rem;font-size:1.35rem;font-weight:800;letter-spacing:-0.02em;color:var(--text-main)" data-counter="${company.appointment_count}">0</div>
                            </div>
                            <div class="stat-block">
                                <div class="stat-label">Dükkan</div>
                                <div style="margin-top:0.5rem">${limitBadge(company.shop_count, company.max_shop_count)}</div>
                            </div>
                            <div class="stat-block">
                                <div class="stat-label">Stüdyo</div>
                                <div style="margin-top:0.5rem">${limitBadge(company.studio_count, company.max_studio_count)}</div>
                            </div>
                        </div>
                        <div style="margin-bottom:1rem">${statBlock('Şirket Yöneticisi', escapeHtml(company.manager?.name || '—'))}</div>
                        <div style="padding-top:0.85rem;border-top:1px solid var(--border)">
                            <form class="form-grid" data-company-edit-form data-company-id="${company.id}" style="gap:0.6rem">
                                <div class="field-wrap"><label class="field-label">Şirket Adı</label><input class="field-input" name="name" value="${escapeHtml(company.name)}"></div>
                                <div class="field-wrap"><label class="field-label">Şirket Yöneticisi</label><select class="field-select" name="manager_user_id">${buildManagerOptions(company.manager_user_id)}</select></div>
                                <div class="field-wrap"><label class="field-label">Adres</label><input class="field-input" name="address" value="${escapeHtml(company.address || '')}"></div>
                                <div class="form-grid form-grid--split">
                                    <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="phone" value="${escapeHtml(company.phone || '')}"></div>
                                    <div class="field-wrap"><label class="field-label">E-posta</label><input class="field-input" name="email" type="email" value="${escapeHtml(company.email || '')}"></div>
                                </div>
                                <div class="form-grid form-grid--split">
                                    <div class="field-wrap"><label class="field-label">Max Dükkan</label><input class="field-input" type="number" min="0" name="max_shop_count" value="${company.max_shop_count}"></div>
                                    <div class="field-wrap"><label class="field-label">Max Stüdyo</label><input class="field-input" type="number" min="0" name="max_studio_count" value="${company.max_studio_count}"></div>
                                </div>
                                <button class="button-primary" type="submit" style="justify-content:center;padding:0.5rem">Kaydet</button>
                            </form>
                        </div>
                    </article>
                `).join('') : '<div class="empty-state">Kayıtlı şirket bulunamadı.</div>'}
            </div>
        `;

        animateCounters(listNode);

        listNode.querySelectorAll('[data-company-edit-form]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                handleAsync(async () => {
                const body = Object.fromEntries(new FormData(form).entries());
                if (!body.manager_user_id) delete body.manager_user_id;
                await apiFetch(`/companies/${form.getAttribute('data-company-id')}`, { method: 'PATCH', body });
                    showToast('Şirket güncellendi.', 'success');
                    await renderCompanies();
                });
            });
        });
    };

    await renderCompanies();

    qs('[data-company-create-form]', root)?.addEventListener('submit', (e) => {
        e.preventDefault();
        handleAsync(async () => {
            const form = e.target;
            const body = Object.fromEntries(new FormData(form).entries());
            await apiFetch('/companies', { method: 'POST', body });
            showToast('Şirket oluşturuldu.', 'success');
            form.reset();
            await renderCompanies();
        });
    });
};

/* ── Talepler ───────────────────────────────────────────────── */

const renderAppointmentRequestsPage = async (root) => {
    const params = new URLSearchParams(window.location.search);
    const initialDirection = isRegularUserRole() ? 'outgoing' : (params.get('direction') || 'incoming');

    root.innerHTML = `
        ${pageHeader('Talep Yönetimi', 'Randevu Talepleri', 'Gelen talepler kabul edilince doğrudan randevuya dönüşür.', '<span class="badge-pill badge-pill--teal">Talep Akışı</span>')}
        <div class="form-shell" style="margin-bottom:1rem">
            <div class="section-eyebrow" style="margin-bottom:0.4rem">Yeni Talep</div>
            <div class="section-title" style="margin-bottom:1rem">Stüdyoya Talep Gönder</div>
            <form class="form-grid" data-request-create-form enctype="multipart/form-data">
                <div class="form-grid form-grid--split">
                    <div class="field-wrap">
                        <label class="field-label">Stüdyo</label>
                        <select class="field-select" name="studio_id" data-request-studio-select required></select>
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Tür</label>
                        <select class="field-select" name="type" required>
                            <option value="designer">Tasarım</option>
                            <option value="tattoo">Dövme</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid form-grid--split">
                    <div class="field-wrap"><label class="field-label">Tarih</label><input class="field-input" name="preferred_date" type="date" required></div>
                    <div class="field-wrap"><label class="field-label">Saat</label><input class="field-input" name="preferred_time" type="time" required></div>
                </div>
                <div class="form-grid form-grid--split">
                    <div class="field-wrap"><label class="field-label">İsim</label><input class="field-input" name="first_name" required></div>
                    <div class="field-wrap"><label class="field-label">Soyisim</label><input class="field-input" name="last_name" required></div>
                </div>
                <div class="form-grid form-grid--split">
                    <div class="field-wrap">
                        <label class="field-label">Ülke Kodu</label>
                        <select class="field-select" name="phone_country_code" required>
                            <option value="+90">🇹🇷 Turkey +90</option>
                            <option value="+49">🇩🇪 Germany +49</option>
                            <option value="+44">🇬🇧 United Kingdom +44</option>
                            <option value="+48">🇵🇱 Poland +48</option>
                            <option value="+31">🇳🇱 Netherlands +31</option>
                            <option value="+7">🇷🇺 Russia +7</option>
                            <option value="+41">🇨🇭 Switzerland +41</option>
                            <option value="+32">🇧🇪 Belgium +32</option>
                            <option value="+372">🇪🇪 Estonia +372</option>
                            <option value="+46">🇸🇪 Sweden +46</option>
                            <option value="+47">🇳🇴 Norway +47</option>
                            <option value="+45">🇩🇰 Denmark +45</option>
                            <option value="+358">🇫🇮 Finland +358</option>
                        </select>
                    </div>
                    <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="phone_number" inputmode="tel" required></div>
                </div>
                <div class="form-grid form-grid--split">
                    <div class="field-wrap"><label class="field-label">Otel</label><input class="field-input" name="hotel_name" required></div>
                    <div class="field-wrap"><label class="field-label">Oda</label><input class="field-input" name="room_number" required></div>
                </div>
                <div class="form-grid form-grid--split">
                    <div class="field-wrap"><label class="field-label">Yer</label><input class="field-input" name="place" required></div>
                    <div class="field-wrap"><label class="field-label">Kişi</label><input class="field-input" name="pax" type="number" min="1" value="1" required></div>
                </div>
                <div class="form-grid form-grid--split">
                    <div class="field-wrap"><label class="field-label">Müşteri Fotoğrafı</label><input class="field-input" name="image" type="file" accept="image/*" required></div>
                    <div class="field-wrap"><label class="field-label">Dövme Görselleri <span style="color:var(--text-subtle)">(en fazla 3)</span></label><input class="field-input" name="tattoo_images[]" type="file" accept="image/*" multiple></div>
                </div>
                <div class="field-wrap"><label class="field-label">Not</label><textarea class="field-input" name="notes" rows="3"></textarea></div>
                <label style="display:flex;align-items:center;gap:0.45rem;font-size:0.78rem;color:var(--text-muted)">
                    <input type="checkbox" name="pickup_required" value="1">
                    Pick up gerekli
                </label>
                <button class="button-primary" type="submit" style="justify-content:center">Talep Gönder</button>
            </form>
        </div>
        <div class="panel-card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                    ${isRegularUserRole() ? '' : '<button class="button-secondary" data-request-tab="incoming" style="padding:0.5rem 0.85rem;font-size:0.78rem">Gelen</button>'}
                    <button class="button-secondary" data-request-tab="outgoing" style="padding:0.5rem 0.85rem;font-size:0.78rem">Gönderdiklerim</button>
                </div>
                <button class="button-secondary" data-requests-refresh style="padding:0.5rem 0.85rem;font-size:0.78rem">Yenile</button>
            </div>
            <div class="list-stack" data-requests-list>${skeletonGrid(4)}</div>
        </div>
    `;

    const listNode = qs('[data-requests-list]', root);
    const createForm = qs('[data-request-create-form]', root);
    const requestStudioSelect = qs('[data-request-studio-select]', root);
    let direction = initialDirection;

    const loadRequestStudios = async () => {
        const payload = await apiFetch('/public/studios').catch(() => ({ data: [] }));
        const studios = payload.data || [];
        requestStudioSelect.innerHTML = studios.map((studio) =>
            `<option value="${studio.id}" ${String(studio.id) === String(params.get('studio_id')) ? 'selected' : ''}>${escapeHtml(studio.name)}</option>`
        ).join('');
    };

    const setActiveTab = () => {
        root.querySelectorAll('[data-request-tab]').forEach((btn) => {
            const active = btn.getAttribute('data-request-tab') === direction;
            btn.classList.toggle('button-primary', active);
            btn.classList.toggle('button-secondary', !active);
        });
    };

    const renderRequests = async () => {
        setActiveTab();
        listNode.innerHTML = skeletonGrid(4);
        const payload = await apiFetch(`/appointment-requests?direction=${encodeURIComponent(direction)}`);
        const requests = payload.data || [];

        listNode.innerHTML = requests.length
            ? requests.map((request, i) => {
                const customerName = `${request.customer?.first_name || request.first_name || ''} ${request.customer?.last_name || request.last_name || ''}`.trim();
                const image = request.image_path || request.tattoo_image_paths?.[0];
                const canRespond = direction === 'incoming' && request.status === 'pending';
                return `
                <article class="list-card animate-stagger-${(i % 3) + 1}" data-request-card data-request-id="${request.id}" style="padding:0.9rem 1rem">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.85rem;flex-wrap:wrap">
                        <div style="display:flex;gap:0.75rem;min-width:240px;flex:1">
                            ${image ? `<a href="${escapeHtml(image)}" target="_blank" rel="noopener noreferrer" style="flex-shrink:0"><img src="${escapeHtml(image)}" alt="Talep görseli" style="width:62px;height:62px;object-fit:cover;border-radius:0.7rem;border:1px solid var(--border)"></a>` : '<div style="width:62px;height:62px;border-radius:0.7rem;background:var(--surface-soft);border:1px solid var(--border);flex-shrink:0"></div>'}
                            <div>
                                <div style="font-weight:700;color:var(--text-main);font-size:0.9rem">${escapeHtml(customerName || request.requester?.name || 'Talep')}</div>
                                <div style="margin-top:0.25rem;font-size:0.74rem;color:var(--text-muted)">${escapeHtml(request.studio?.name || request.target?.name || 'Bağımsız')} · ${formatDateTime(request.requested_at)}</div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-subtle)">Otel: ${escapeHtml(request.hotel_name || request.customer?.hotel_name || '—')} · Oda: ${escapeHtml(request.room_number || request.customer?.room_number || '—')}</div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-subtle)">Telefon: ${escapeHtml(`${request.phone_country_code || request.customer?.phone_country_code || ''} ${request.phone_number || request.customer?.phone_number || ''}`.trim() || '—')}</div>
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.35rem">
                            <span class="${requestStatusClass(request.status)}" style="font-size:0.65rem">${requestStatusLabel(request.status)}</span>
                            <span class="badge-pill badge-pill--teal" style="font-size:0.6rem">${APPOINTMENT_TYPE_LABELS[request.request_type] || request.request_type}</span>
                            ${request.price !== null && request.price !== undefined ? `<span class="badge-pill" style="font-size:0.6rem">${escapeHtml(request.price)} ₺</span>` : ''}
                        </div>
                    </div>
                    ${request.notes ? `<div style="margin-top:0.85rem;font-size:0.78rem;color:var(--text-muted);line-height:1.55">${escapeHtml(request.notes)}</div>` : ''}
                    ${request.tattoo_image_paths?.length ? `
                        <div style="display:flex;gap:0.45rem;flex-wrap:wrap;margin-top:0.75rem">
                            ${request.tattoo_image_paths.map((path) => `<a href="${escapeHtml(path)}" target="_blank" rel="noopener noreferrer"><img src="${escapeHtml(path)}" alt="Dövme görseli" style="width:48px;height:48px;object-fit:cover;border-radius:0.55rem;border:1px solid var(--border)"></a>`).join('')}
                        </div>
                    ` : ''}
                    <div style="display:flex;align-items:flex-end;gap:0.6rem;flex-wrap:wrap;margin-top:0.85rem;padding-top:0.85rem;border-top:1px solid var(--border)">
                        ${canRespond ? `
                            <div class="field-wrap" style="max-width:160px">
                                <label class="field-label">Fiyat</label>
                                <input class="field-input" data-request-price type="number" min="0" step="0.01" value="${escapeHtml(request.price ?? '')}" required>
                            </div>
                            <button class="button-primary" data-request-accept style="padding:0.45rem 0.85rem;font-size:0.75rem">Kabul Et</button>
                            <button class="button-ghost" data-request-reject style="padding:0.45rem 0.85rem;font-size:0.75rem">Reddet</button>
                        ` : ''}
                        ${request.appointment_id ? `<a href="/admin/appointments/${request.appointment_id}" class="button-secondary" style="padding:0.45rem 0.85rem;font-size:0.75rem">Randevuya Git</a>` : ''}
                    </div>
                </article>
            `}).join('')
            : `<div class="empty-state">${direction === 'incoming' ? 'Gelen talep bulunmuyor.' : 'Gönderilmiş talep bulunmuyor.'}</div>`;

        listNode.querySelectorAll('[data-request-accept]').forEach((button) => {
            button.addEventListener('click', () => handleAsync(async () => {
                const card = button.closest('[data-request-card]');
                const price = qs('[data-request-price]', card)?.value;
                if (!price) throw new Error('Talebi kabul etmek için fiyat girin.');
                await apiFetch(`/appointment-requests/${card?.getAttribute('data-request-id')}/accept`, {
                    method: 'PATCH',
                    body: { price },
                });
                showToast('Talep kabul edildi ve randevuya dönüştü.', 'success');
                await renderRequests();
            }));
        });

        listNode.querySelectorAll('[data-request-reject]').forEach((button) => {
            button.addEventListener('click', () => handleAsync(async () => {
                const card = button.closest('[data-request-card]');
                await apiFetch(`/appointment-requests/${card?.getAttribute('data-request-id')}/reject`, {
                    method: 'PATCH',
                    body: {},
                });
                showToast('Talep reddedildi.', 'success');
                await renderRequests();
            }));
        });
    };

    root.querySelectorAll('[data-request-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            direction = button.getAttribute('data-request-tab') || 'incoming';
            handleAsync(renderRequests);
        });
    });
    qs('[data-requests-refresh]', root)?.addEventListener('click', () => handleAsync(renderRequests));
    createForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        handleAsync(async () => {
            const tattooFiles = createForm.querySelector('input[name="tattoo_images[]"]')?.files;
            if (tattooFiles && tattooFiles.length > 3) {
                throw new Error('En fazla 3 dövme görseli ekleyebilirsiniz.');
            }
            const formData = new FormData(createForm);
            const normalized = new FormData();
            for (const [key, value] of formData.entries()) {
                normalized.append(key === 'tattoo_images[]' ? 'tattoo_images[]' : key, value);
            }
            await apiFetch('/appointments/request', { method: 'POST', body: normalized });
            showToast('Talep gönderildi.', 'success');
            createForm.reset();
            direction = isRegularUserRole() ? 'outgoing' : direction;
            await renderRequests();
        });
    });

    await loadRequestStudios();
    await renderRequests();
};

/* ── Kullanıcı Bildirimleri ─────────────────────────────────── */

const notificationTargetUrl = (notification) => {
    const data = notification.data || {};
    if (data.appointment_id) return `/admin/appointments/${encodeURIComponent(data.appointment_id)}`;
    if (data.appointment_request_id) return `/admin/appointment-requests?request_id=${encodeURIComponent(data.appointment_request_id)}`;
    if (data.invitation_id) return '/admin/my-notifications';
    return null;
};

const renderMyNotificationsPage = async (root) => {
    root.innerHTML = `
        ${pageHeader('Bildirimler', 'Bildirimlerim', 'Size gelen sistem bildirimleri ve çalışanlık davetleri.', '<span class="badge-pill badge-pill--info">Canlı</span>')}
        <div class="panel-card" style="margin-bottom:1rem">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                <div>
                    <div class="section-title">Bildirim Akışı</div>
                    <div style="margin-top:0.25rem;font-size:0.75rem;color:var(--text-muted)">Bildirime tıklayınca ilgili ekrana yönlendirilir.</div>
                </div>
                <button class="button-secondary" data-notifications-read-all style="padding:0.5rem 0.85rem;font-size:0.78rem">Tümünü Okundu Yap</button>
            </div>
        </div>
        <div class="panel-card" data-invitations-list style="margin-bottom:1rem">${skeletonGrid(1)}</div>
        <div class="list-stack" data-notifications-list>${skeletonGrid(4)}</div>
    `;

    const listNode = qs('[data-notifications-list]', root);
    const invitationsNode = qs('[data-invitations-list]', root);

    const renderInvitations = async () => {
        const payload = await apiFetch('/staff-invitations').catch(() => ({ data: [] }));
        const invitations = payload.data || [];
        invitationsNode.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem">
                <div>
                    <div class="section-eyebrow" style="margin-bottom:0.25rem">Çalışanlık</div>
                    <div class="section-title">Davetler</div>
                </div>
                <span class="badge-pill">${invitations.length} davet</span>
            </div>
            <div class="list-stack">
                ${invitations.map((invitation) => `
                    <div class="list-card" data-invitation-id="${invitation.id}" style="padding:0.75rem 0.85rem">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;flex-wrap:wrap">
                            <div>
                                <div style="font-weight:650;color:var(--text-main);font-size:0.84rem">${escapeHtml(invitation.studio?.name || 'Stüdyo')}</div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">${roleLabel(invitation.role)} · ${requestStatusLabel(invitation.status)}</div>
                            </div>
                            ${invitation.status === 'pending' ? `
                                <div style="display:flex;gap:0.45rem">
                                    <button class="button-primary" data-invitation-accept style="padding:0.38rem 0.7rem;font-size:0.72rem">Kabul</button>
                                    <button class="button-ghost" data-invitation-reject style="padding:0.38rem 0.7rem;font-size:0.72rem">Reddet</button>
                                </div>
                            ` : `<span class="${requestStatusClass(invitation.status)}" style="font-size:0.62rem">${requestStatusLabel(invitation.status)}</span>`}
                        </div>
                    </div>
                `).join('') || '<div class="empty-state" style="padding:1rem;border:none">Davet bulunmuyor.</div>'}
            </div>
        `;

        invitationsNode.querySelectorAll('[data-invitation-accept]').forEach((button) => {
            button.addEventListener('click', () => handleAsync(async () => {
                const id = button.closest('[data-invitation-id]')?.getAttribute('data-invitation-id');
                await apiFetch(`/staff-invitations/${id}/accept`, { method: 'PATCH', body: {} });
                showToast('Davet kabul edildi.', 'success');
                await renderInvitations();
            }));
        });
        invitationsNode.querySelectorAll('[data-invitation-reject]').forEach((button) => {
            button.addEventListener('click', () => handleAsync(async () => {
                const id = button.closest('[data-invitation-id]')?.getAttribute('data-invitation-id');
                await apiFetch(`/staff-invitations/${id}/reject`, { method: 'PATCH', body: {} });
                showToast('Davet reddedildi.', 'success');
                await renderInvitations();
            }));
        });
    };

    const renderNotifications = async () => {
        listNode.innerHTML = skeletonGrid(4);
        const payload = await apiFetch('/notifications');
        const unread = payload.data?.unread || [];
        const read = payload.data?.read || [];
        const notifications = [...unread, ...read];

        listNode.innerHTML = notifications.length
            ? notifications.map((notification, i) => `
                <button type="button" class="list-card animate-stagger-${(i % 3) + 1}" data-notification-id="${notification.id}" style="width:100%;text-align:left;padding:0.85rem 1rem;cursor:pointer;${notification.isRead ? 'opacity:0.72' : ''}">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem">
                        <div>
                            <div style="font-size:0.86rem;font-weight:700;color:var(--text-main)">${escapeHtml(notification.title)}</div>
                            <div style="margin-top:0.25rem;font-size:0.75rem;color:var(--text-muted);line-height:1.45">${escapeHtml(notification.body || notification.description || '')}</div>
                            <div style="margin-top:0.25rem;font-size:0.68rem;color:var(--text-subtle)">${escapeHtml(notification.time || formatDateTime(notification.created_at))}</div>
                        </div>
                        <span class="badge-pill ${notification.isRead ? '' : 'badge-pill--info'}" style="font-size:0.6rem">${notification.isRead ? 'Okundu' : 'Yeni'}</span>
                    </div>
                </button>
            `).join('')
            : '<div class="empty-state">Bildirim bulunmuyor.</div>';

        listNode.querySelectorAll('[data-notification-id]').forEach((button) => {
            button.addEventListener('click', () => handleAsync(async () => {
                const id = button.getAttribute('data-notification-id');
                const notification = notifications.find((item) => String(item.id) === String(id));
                await apiFetch(`/notifications/${id}/read`, { method: 'PATCH', body: {} });
                const url = notificationTargetUrl(notification);
                if (url) {
                    window.location.href = url;
                    return;
                }
                await renderNotifications();
            }));
        });
    };

    qs('[data-notifications-read-all]', root)?.addEventListener('click', () => handleAsync(async () => {
        await apiFetch('/notifications/read-all', { method: 'PATCH', body: {} });
        showToast('Bildirimler okundu.', 'success');
        await renderNotifications();
    }));

    await Promise.all([renderInvitations(), renderNotifications()]);
};

/* ── Sayfa yönlendirici ─────────────────────────────────────── */

const pageInitializers = [
    ['[data-admin-dashboard]',    renderDashboard],
    ['[data-admin-companies]',    renderCompaniesPage],
    ['[data-admin-users]',        renderUsersPage],
    ['[data-admin-appointments]', renderAppointmentsPage],
    ['[data-admin-appointment-requests]', renderAppointmentRequestsPage],
    ['[data-admin-my-notifications]', renderMyNotificationsPage],
    ['[data-admin-studios]',      renderStudiosPage],
    ['[data-admin-shops]',        renderShopsPage],
];

/* ── Firebase Web Push ──────────────────────────────────────── */

const initWebPush = async () => {
    if (!adminConfig.token || !firebaseWebConfig.apiKey) return;
    if (!('serviceWorker' in navigator) || !('Notification' in window)) return;

    try {
        const permission = Notification.permission === 'default'
            ? await Notification.requestPermission()
            : Notification.permission;

        if (permission !== 'granted') return;

        const [
            { initializeApp },
            { getAnalytics, isSupported: isAnalyticsSupported },
            { getMessaging, getToken, onMessage, isSupported: isMessagingSupported },
        ] = await Promise.all([
            import(/* @vite-ignore */ 'https://www.gstatic.com/firebasejs/12.7.0/firebase-app.js'),
            import(/* @vite-ignore */ 'https://www.gstatic.com/firebasejs/12.7.0/firebase-analytics.js'),
            import(/* @vite-ignore */ 'https://www.gstatic.com/firebasejs/12.7.0/firebase-messaging.js'),
        ]);

        if (!await isMessagingSupported()) return;

        const app = initializeApp(firebaseWebConfig);
        if (await isAnalyticsSupported()) getAnalytics(app);

        const swReg     = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
        const messaging = getMessaging(app);
        const token     = await getToken(messaging, {
            serviceWorkerRegistration: swReg,
            ...(firebaseWebVapidKey ? { vapidKey: firebaseWebVapidKey } : {}),
        });

        if (token) {
            await apiFetch('/push-tokens', { method: 'POST', body: { token, platform: 'web' } });
        }

        onMessage(messaging, (payload) => {
            const title = payload.notification?.title || 'Yeni bildirim';
            const body  = payload.notification?.body  || 'Randevu bildirimi alındı.';
            showToast(`${title}: ${body}`, 'info');
        });
    } catch (error) {
        console.warn('Firebase web push kaydı yapılamadı.', error);
    }
};

/* ── Başlat ─────────────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', () => {
    initWebPush();

    pageInitializers.forEach(([selector, initializer]) => {
        const root = qs(selector);
        if (!root) return;
        handleAsync(() => initializer(root), 'Panel verileri yüklenemedi.');
    });
});
