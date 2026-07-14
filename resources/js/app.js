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
    userId:             meta('admin-user-id'),
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
    in_progress: 'Devam Ediyor',
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
    designer: 'Randevu',
    tattoo:   'Bilet',
};

const TICKET_TYPE_LABELS = {
    cream_sale:       'Krem satışı',
    piercing:         'Piercing',
    tattoo:           'Dövme',
    piercing_service: 'Piercing yapımı',
};

const TATTOO_TYPE_LABELS = {
    coverup:  'Coverup',
    freehand: 'Freehand',
    refresh:  'Refresh',
    touchub:  'Touchub',
    clean:    'Clean',
};

const PAYMENT_METHOD_LABELS = {
    credit_card: 'Kredi kartı',
    cash:        'Nakit',
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
const canCreateAppointmentWeb = () =>
    ['yonetici', 'supervisor', 'designer', 'info', 'calisan'].includes(adminConfig.role);

const ticketMetaLine = (item = {}) => {
    if (item.appointment_type !== 'tattoo' && item.request_type !== 'tattoo') return '';
    const types = (item.ticket_type_labels || item.ticket_types?.map((type) => TICKET_TYPE_LABELS[type] || type) || []).join(', ');
    const tattooType = item.tattoo_type_label || TATTOO_TYPE_LABELS[item.tattoo_type] || item.tattoo_type || '';
    const payment = item.payment_method_label || PAYMENT_METHOD_LABELS[item.payment_method] || item.payment_method || '';
    return [types, tattooType, payment].filter(Boolean).join(' · ');
};

const ticketFieldsMarkup = () => `
    <div class="form-grid" data-ticket-fields style="display:none">
        <div class="field-wrap">
            <label class="field-label">Bilet Türü</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.5rem">
                ${Object.entries(TICKET_TYPE_LABELS).map(([value, label]) => `
                    <label class="list-card" style="display:flex;align-items:center;gap:0.45rem;padding:0.65rem 0.75rem;font-size:0.78rem;color:var(--text-muted)">
                        <input type="checkbox" name="ticket_types[]" value="${value}" data-ticket-input disabled>
                        <span>${escapeHtml(label)}</span>
                    </label>
                `).join('')}
            </div>
        </div>
        <div class="form-grid form-grid--split">
            <div class="field-wrap">
                <label class="field-label">Dövme Türü</label>
                <select class="field-select" name="tattoo_type" data-ticket-input disabled>
                    <option value="">Seç</option>
                    ${Object.entries(TATTOO_TYPE_LABELS).map(([value, label]) => `<option value="${value}">${escapeHtml(label)}</option>`).join('')}
                </select>
            </div>
            <div class="field-wrap">
                <label class="field-label">Ödeme Yöntemi</label>
                <select class="field-select" name="payment_method" data-ticket-input disabled>
                    <option value="">Seç</option>
                    ${Object.entries(PAYMENT_METHOD_LABELS).map(([value, label]) => `<option value="${value}">${escapeHtml(label)}</option>`).join('')}
                </select>
            </div>
        </div>
        <div class="field-wrap">
            <label class="field-label">Depozito <span style="color:var(--text-subtle)">(opsiyonel)</span></label>
            <input class="field-input" name="deposit_amount" type="number" min="0" step="0.01" data-ticket-input disabled>
        </div>
    </div>
`;

const bindTicketFields = (form, typeName = 'appointment_type') => {
    const typeSelect = form?.querySelector(`[name="${typeName}"]`);
    const wrapper = form?.querySelector('[data-ticket-fields]');
    if (!typeSelect || !wrapper) return;

    const sync = () => {
        const enabled = typeSelect.value === 'tattoo';
        wrapper.style.display = enabled ? 'grid' : 'none';
        wrapper.querySelectorAll('[data-ticket-input]').forEach((input) => {
            input.disabled = !enabled;
            if (!enabled) {
                if (input.type === 'checkbox') input.checked = false;
                else input.value = '';
            }
        });
    };

    typeSelect.addEventListener('change', sync);
    sync();
};

const bindDesignerAppointmentFields = (form, typeName = 'appointment_type') => {
    const typeSelect = form?.querySelector(`[name="${typeName}"]`);
    if (!typeSelect) return;

    const priceWrap = form.querySelector('[data-price-field]');
    const priceInput = form.querySelector('[name="price"]');
    const pickupWrap = form.querySelector('[data-pickup-field]');
    const pickupInput = form.querySelector('[name="pickup_required"]');
    const imageLabel = form.querySelector('[data-appointment-images-label]');

    const sync = () => {
        const isDesigner = typeSelect.value === 'designer';
        if (priceWrap) priceWrap.style.display = isDesigner ? 'none' : '';
        if (priceInput && isDesigner) priceInput.value = '';
        if (pickupWrap) pickupWrap.style.display = isDesigner ? 'none' : 'flex';
        if (pickupInput) {
            pickupInput.checked = isDesigner;
            pickupInput.disabled = isDesigner;
        }
        if (imageLabel) {
            imageLabel.innerHTML = isDesigner
                ? 'Tasarım Görselleri <span style="color:var(--text-subtle)">(sınırsız)</span>'
                : 'Dövme Görselleri <span style="color:var(--text-subtle)">(en fazla 3)</span>';
        }
    };

    typeSelect.addEventListener('change', sync);
    sync();
};

const validateTicketFields = (form, typeName = 'appointment_type') => {
    if (form?.querySelector(`[name="${typeName}"]`)?.value !== 'tattoo') return;
    if (form.querySelectorAll('input[name="ticket_types[]"]:checked').length === 0) {
        throw new Error('Bilet türü seçin.');
    }
    if (!form.querySelector('[name="tattoo_type"]')?.value) {
        throw new Error('Dövme türü seçin.');
    }
    if (!form.querySelector('[name="payment_method"]')?.value) {
        throw new Error('Ödeme yöntemi seçin.');
    }
};

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

const formatMoney = (value) =>
    new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
    }).format(Number(value || 0));

/* ── Durum badge CSS ────────────────────────────────────────── */

const statusClass = (status) => ({
    completed:   'badge-pill badge-pill--success',
    confirmed:   'badge-pill badge-pill--info',
    in_progress: 'badge-pill badge-pill--purple',
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

const percentOf = (value, total) => {
    const safeTotal = Math.max(0, Number(total || 0));
    if (safeTotal === 0) return 0;
    return Math.min(100, Math.round((Number(value || 0) / safeTotal) * 100));
};

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
                            <div style="margin-top:0.25rem;font-size:0.75rem;color:var(--text-muted)">${escapeHtml(studio.location || 'Konum yok')}</div>
                        </div>
                        <span class="badge-pill" style="font-size:0.62rem">${escapeHtml(studio.company?.name || 'Stüdyo')}</span>
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
    const locksToOwnStudio = adminConfig.isSupervisor;
    root.innerHTML = `
        ${pageHeader('Şirket Yönetimi', 'Operasyon Merkezi', 'Stüdyoların, ekiplerin ve randevu operasyonunun güncel görünümü.', '<span class="badge-pill badge-pill--success"><span class="state-dot state-dot--success"></span> Güncel</span>')}
        <div class="business-command-bar">
            <div class="business-command-bar__label">
                <span class="section-eyebrow">Hızlı İşlemler</span>
                <span>Günlük yönetim akışlarına doğrudan erişin</span>
            </div>
            <div class="action-row">
                ${adminConfig.isAdmin ? '<a href="/admin/companies" class="button-secondary">Şirketler</a>' : ''}
                ${adminConfig.canManageStudios ? '<a href="/admin/studios" class="button-secondary">Stüdyolar</a>' : ''}
                <a href="/admin/appointments" class="button-primary">Randevu / Biletleri Aç</a>
            </div>
        </div>
        <div class="panel-card business-filter-bar">
            <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
                <div>
                    <div class="section-eyebrow" style="margin-bottom:0.2rem">Raporlama Kapsamı</div>
                    <div style="font-size:0.78rem;color:var(--text-muted)">Şirket geneli veya tek stüdyo performansı</div>
                </div>
                <select data-dashboard-studio-filter style="margin-left:auto;min-width:220px;${locksToOwnStudio ? 'display:none' : ''}">
                    <option value="">Şirket geneli</option>
                </select>
                ${locksToOwnStudio ? '<span class="badge-pill" data-dashboard-locked-studio>Stüdyo yükleniyor...</span>' : ''}
            </div>
        </div>
        ${adminConfig.isAdmin ? `<div class="panel-card" data-dashboard-companies>${skeletonGrid(2)}</div>` : ''}
        <div class="metric-grid" data-dashboard-metrics>${skeletonGrid(3)}</div>
        <div class="panel-card dashboard-period-chart" data-dashboard-reports>${skeletonGrid(3)}</div>
        <div class="panel-card" data-dashboard-ticket-appointment-chart>${skeletonGrid(3)}</div>
        <div class="panel-card" data-dashboard-finance>${skeletonGrid(2)}</div>
        <div class="panel-card" data-dashboard-hotels>${skeletonGrid(1)}</div>
        <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));align-items:start">
            <div class="panel-card" data-dashboard-studios>${skeletonGrid(1)}</div>
            <div class="panel-card" data-dashboard-appointments>${skeletonGrid(1)}</div>
        </div>
    `;

    const studioPayload = await apiFetch('/studios/options').catch(() => ({ data: [] }));
    const studioOptions = studioPayload.data || [];
    if (locksToOwnStudio && !selectedStudioId && studioOptions[0]?.id) {
        selectedStudioId = String(studioOptions[0].id);
    }
    const studioSelect = qs('[data-dashboard-studio-filter]', root);
    const lockedStudioLabel = qs('[data-dashboard-locked-studio]', root);
    studioSelect.innerHTML = `
        <option value="">Şirket geneli</option>
        ${studioOptions.map((studio) => `<option value="${studio.id}">${escapeHtml(studio.name)}</option>`).join('')}
    `;
    studioSelect.value = selectedStudioId;
    if (lockedStudioLabel) {
        lockedStudioLabel.textContent = studioOptions[0]?.name
            ? `Stüdyo: ${studioOptions[0].name}`
            : 'Atanmış stüdyo bulunamadı';
    }
    if (!locksToOwnStudio) {
        studioSelect.addEventListener('change', () => renderDashboard(root, studioSelect.value));
    }

    const payload = await apiFetch(`/home${selectedStudioId ? `?studio_id=${encodeURIComponent(selectedStudioId)}` : ''}`);
    if (payload.type === 'discovery') {
        renderDiscoveryHome(root, payload.data || {});
        return;
    }
    const data    = payload.data;

    qs('[data-dashboard-metrics]', root).innerHTML = [
        ['Toplam Kayıt',    data.summary.total_appointments,    'Randevu + bilet',       '',                '1'],
        ['Toplam Randevu',  data.summary.design_appointments,   'Sadece tasarım',        'var(--success)',  '2'],
        ['Toplam Bilet',    data.summary.ticket_appointments,   'Dövme / piercing',      'var(--purple)',   '3'],
        ['İptal Edilen',    data.summary.cancelled_appointments,'Operasyon riski',       'var(--danger)',   '4'],
        ['Transfer Görevi', data.summary.transfer_count,        'Planlanan transfer',    'var(--info)',     '5'],
    ].map(([label, value, helper, color, delay]) => metricCard(label, value, helper, color, delay)).join('');

    const periodReports = Object.values(data.reports || {});
    const maxPeriodTotal = Math.max(1, ...periodReports.map((report) => Number(report.total_appointments || 0)));
    const maxPeriodMetric = Math.max(
        1,
        ...periodReports.flatMap((report) => [
            Number(report.total_appointments || 0),
            Number(report.completed_appointments || 0),
            Number(report.confirmed_appointments ?? report.active_appointments ?? 0),
            Number(report.cancelled_appointments || 0),
        ])
    );

    qs('[data-dashboard-reports]', root).innerHTML = `
        <div class="dashboard-period-chart__head">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.3rem">Rapor Grafiği</div>
                <div class="section-title">Günlük / Aylık / Yıllık</div>
            </div>
            <div class="dashboard-period-chart__legend">
                <span><i style="background:var(--accent)"></i>Toplam</span>
                <span><i style="background:var(--success)"></i>Tamamlanan</span>
                <span><i style="background:var(--warning)"></i>Aktif</span>
                <span><i style="background:var(--danger)"></i>İptal</span>
            </div>
        </div>
        <div class="dashboard-period-chart__plot">
            ${periodReports.map((report, i) => {
                const total = Number(report.total_appointments || 0);
                const completed = Number(report.completed_appointments || 0);
                const active = Number(report.confirmed_appointments ?? report.active_appointments ?? 0);
                const cancelled = Number(report.cancelled_appointments || 0);
                const bars = [
                    ['Toplam', total, 'var(--accent)', 'dashboard-period-bar--total'],
                    ['Tamamlanan', completed, 'var(--success)', 'dashboard-period-bar--completed'],
                    ['Aktif', active, 'var(--warning)', 'dashboard-period-bar--active'],
                    ['İptal', cancelled, 'var(--danger)', 'dashboard-period-bar--cancelled'],
                ];
                return `
                    <div class="dashboard-period-group animate-stagger-${(i % 3) + 1}">
                        <div class="dashboard-period-group__bars">
                            ${bars.map(([label, value, color, className]) => {
                                const height = Number(value || 0) > 0 ? Math.max(18, Math.round((Number(value || 0) / maxPeriodMetric) * 190)) : 8;
                                return `
                                    <div class="dashboard-period-bar-wrap" title="${escapeHtml(report.label)} ${label}: ${Number(value || 0)}">
                                        <span>${Number(value || 0).toLocaleString('tr-TR')}</span>
                                        <div class="dashboard-period-bar ${className}" style="height:${height}px;background:${color}"></div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                        <div class="dashboard-period-group__label">
                            <strong>${escapeHtml(report.label)}</strong>
                            <span>${escapeHtml(report.date_from)} - ${escapeHtml(report.date_to)}</span>
                        </div>
                    </div>
                `;
            }).join('') || '<div class="empty-state" style="padding:1.5rem;border:none">Rapor grafiği için kayıt bulunmuyor.</div>'}
        </div>
    `;

    qs('[data-dashboard-ticket-appointment-chart]', root).innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.3rem">Grafik</div>
                <div class="section-title">Genel Bilet / Randevu Grafikleri</div>
            </div>
            <span class="badge-pill">Günlük · Aylık · Yıllık</span>
        </div>
        <div class="appointment-ratio-grid">
            ${periodReports.map((report, i) => {
                const total = Number(report.total_appointments || 0);
                const appointments = Number(report.designer_appointments || 0);
                const tickets = Number(report.ticket_appointments || 0);
                const appointmentsRate = percentOf(appointments, total);
                const ticketsRate = percentOf(tickets, total);
                const appointmentHeight = appointments > 0 ? Math.max(18, Math.round((appointments / maxPeriodTotal) * 132)) : 10;
                const ticketHeight = tickets > 0 ? Math.max(18, Math.round((tickets / maxPeriodTotal) * 132)) : 10;
                return `
                    <article class="data-card appointment-ratio-card animate-stagger-${(i % 3) + 1}">
                        <div class="appointment-ratio-card__head">
                            <div>
                                <div class="section-title" style="font-size:1rem">${escapeHtml(report.label)}</div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">${escapeHtml(report.date_from)} — ${escapeHtml(report.date_to)}</div>
                            </div>
                            <span class="badge-pill">${total.toLocaleString('tr-TR')} kayıt</span>
                        </div>
                        <div class="appointment-ratio-visual">
                            <div class="appointment-ratio-bars appointment-ratio-bars--split">
                                <div class="appointment-ratio-bar-column">
                                    <div class="appointment-ratio-mainbar" style="height:${appointmentHeight}px" title="${escapeHtml(report.label)} randevu: ${appointments}">
                                        <span>${appointments.toLocaleString('tr-TR')}</span>
                                    </div>
                                    <div class="appointment-ratio-axis">Randevu</div>
                                </div>
                                <div class="appointment-ratio-bar-column">
                                    <div class="appointment-ratio-mainbar appointment-ratio-mainbar--ticket" style="height:${ticketHeight}px" title="${escapeHtml(report.label)} bilet: ${tickets}">
                                        <span>${tickets.toLocaleString('tr-TR')}</span>
                                    </div>
                                    <div class="appointment-ratio-axis">Bilet</div>
                                </div>
                            </div>
                        </div>
                        <div class="appointment-ratio-lines">
                            ${[
                                ['Randevu', appointments, appointmentsRate, 'var(--accent)'],
                                ['Bilet', tickets, ticketsRate, 'var(--purple)'],
                            ].map(([label, value, rate, color]) => `
                                <div class="appointment-ratio-line">
                                    <div class="appointment-ratio-line__meta">
                                        <span>${label}</span>
                                        <strong>${rate}% · ${Number(value || 0).toLocaleString('tr-TR')}</strong>
                                    </div>
                                    <div class="appointment-ratio-track">
                                        <div style="width:${rate}%;background:${color}"></div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </article>
                `;
            }).join('') || '<div class="empty-state" style="padding:1.5rem;border:none">Bilet / randevu grafiği için kayıt bulunmuyor.</div>'}
        </div>
    `;

    const studioRevenues = data.studio_revenues || [];
    const companyRevenues = data.company_revenues || [];
    const hotelSources = data.hotel_sources || [];

    qs('[data-dashboard-finance]', root).innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.3rem">Finans</div>
                <div class="section-title">Ciro Özeti</div>
            </div>
            <span class="badge-pill">Bu ay</span>
        </div>
        <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));margin-bottom:1rem">
            <div class="table-shell">
                <table>
                    <thead>
                        <tr>
                            <th>Stüdyo</th>
                            <th>Randevu</th>
                            <th>Ciro</th>
                            <th>Tamamlanan</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${studioRevenues.map((studio) => `
                            <tr>
                                <td>
                                    <div style="font-weight:600">${escapeHtml(studio.name || '—')}</div>
                                    <div style="font-size:0.7rem;color:var(--text-muted)">${escapeHtml(studio.company_name || 'Şirket yok')}</div>
                                </td>
                                <td>${studio.appointment_count || 0}</td>
                                <td style="font-weight:700">${formatMoney(studio.revenue)}</td>
                                <td>${formatMoney(studio.completed_revenue)}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="4" style="color:var(--text-muted);text-align:center;padding:1.5rem">Ciro kaydı bulunamadı.</td></tr>'}
                    </tbody>
                </table>
            </div>
            <div class="table-shell">
                <table>
                    <thead>
                        <tr>
                            <th>Şirket</th>
                            <th>Stüdyo</th>
                            <th>Ciro</th>
                            <th>Tamamlanan</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${companyRevenues.map((company) => `
                            <tr>
                                <td style="font-weight:600">${escapeHtml(company.name || '—')}</td>
                                <td>${company.studio_count || 0}</td>
                                <td style="font-weight:700">${formatMoney(company.revenue)}</td>
                                <td>${formatMoney(company.completed_revenue)}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="4" style="color:var(--text-muted);text-align:center;padding:1.5rem">Şirket cirosu bulunamadı.</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    qs('[data-dashboard-hotels]', root).innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.3rem">Müşteri Kaynağı</div>
                <div class="section-title">Otele Göre Gelen Müşteriler</div>
            </div>
            <span class="badge-pill">${hotelSources.length} otel</span>
        </div>
        <div class="table-shell">
            <table>
                <thead>
                    <tr>
                        <th>Otel</th>
                        <th>Müşteri</th>
                        <th>Randevu</th>
                        <th>Ciro</th>
                    </tr>
                </thead>
                <tbody>
                    ${hotelSources.map((hotel) => `
                        <tr>
                            <td style="font-weight:600">${escapeHtml(hotel.hotel_name || 'Belirtilmeyen')}</td>
                            <td><span class="badge-pill badge-pill--success" style="font-size:0.65rem">${hotel.customer_count || 0}</span></td>
                            <td>${hotel.appointment_count || 0}</td>
                            <td style="font-weight:700">${formatMoney(hotel.revenue)}</td>
                        </tr>
                    `).join('') || '<tr><td colspan="4" style="color:var(--text-muted);text-align:center;padding:1.5rem">Otel kaynaklı müşteri kaydı bulunamadı.</td></tr>'}
                </tbody>
            </table>
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
                        <th>Randevu</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.studios.map((studio) => `
                        <tr>
                            <td style="font-weight:600">${escapeHtml(studio.name)}</td>
                            <td style="color:var(--text-muted)">${escapeHtml(studio.location || '—')}</td>
                            <td style="font-weight:600">${studio.appointments_count}</td>
                        </tr>
                    `).join('') || '<tr><td colspan="3" style="color:var(--text-muted);text-align:center;padding:1.5rem">Stüdyo bulunamadı.</td></tr>'}
                </tbody>
            </table>
        </div>
    `;

    qs('[data-dashboard-appointments]', root).innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.3rem">Günlük</div>
                <div class="section-title">Bugünün Randevu / Biletleri</div>
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
            `).join('') || '<div class="empty-state" style="padding:1.5rem;border:none">Bugün için kayıt bulunmuyor.</div>'}
        </div>
    `;

    if (adminConfig.isAdmin) {
        const compPayload = await apiFetch('/companies').catch(() => ({ data: [] }));
        const companies   = compPayload.data || [];
        qs('[data-dashboard-companies]', root).innerHTML = `
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
                <div>
                    <div class="section-eyebrow" style="margin-bottom:0.3rem">Platform</div>
                    <div class="section-title">Şirket Kayıt Hacimleri</div>
                </div>
                <span class="badge-pill">${companies.length} şirket</span>
            </div>
            <div class="table-shell">
                <table>
                    <thead>
                        <tr>
                            <th>Şirket Adı</th>
                            <th>Stüdyo</th>
                            <th>Randevu</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${companies.map((company) => `
                            <tr>
                                <td style="font-weight:600">${escapeHtml(company.name)}</td>
                                <td style="color:var(--text-muted)">${company.studio_count} / ${company.max_studio_count === 0 ? '∞' : company.max_studio_count}</td>
                                <td style="font-weight:700" data-counter="${company.appointment_count}">0</td>
                            </tr>
                        `).join('') || '<tr><td colspan="3" style="color:var(--text-muted);text-align:center;padding:1.5rem">Şirket bulunamadı.</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    }

    animateCounters(root);
};

/* ── Kullanıcılar ───────────────────────────────────────────── */

const renderUsersPage = async (root) => {
    const locksToOwnStudio = adminConfig.isSupervisor;
    const roles = adminConfig.isAdmin
        ? ['admin', 'yonetici', 'supervisor', 'designer', 'artist', 'info', 'sofor', 'calisan']
        : adminConfig.canManageShops
        ? ['supervisor', 'designer', 'artist', 'info', 'sofor', 'calisan']
        : ['designer', 'artist', 'info', 'sofor', 'calisan'];

    const canEditUserInfo = (user) =>
        adminConfig.isAdmin || String(user.id) === String(adminConfig.userId);

    root.innerHTML = `
        ${pageHeader('Ekip Yönetimi', 'Personel & Kullanıcılar', 'Personel listesi, roller ve stüdyo atamaları tek panelde.', '<span class="badge-pill badge-pill--purple">Ekip</span>')}
        <div style="display:grid;gap:1rem;grid-template-columns:1fr 1fr">
            <div class="panel-card">
                <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem">
                    <div class="field-wrap" data-users-studio-wrap style="flex:1;min-width:0;${locksToOwnStudio ? 'display:none' : ''}">
                        <label class="field-label">Stüdyo Seç</label>
                        <select class="field-select" data-users-studio-select></select>
                    </div>
                    ${locksToOwnStudio ? '<div class="badge-pill" data-users-locked-studio>Stüdyo yükleniyor...</div>' : ''}
                    <button class="button-secondary" data-users-refresh style="padding:0.55rem 0.85rem;font-size:0.78rem;flex-shrink:0">Yenile</button>
                </div>
                <div class="list-stack" data-users-list>${skeletonGrid(4)}</div>
            </div>
            ${adminConfig.canManageUsers ? `
            <div class="form-shell" style="align-self:start">
                <div class="section-eyebrow" style="margin-bottom:0.4rem">Personel Ekle</div>
                <div class="section-title" style="margin-bottom:0.3rem">ID ile İş Teklifi</div>
                <p style="font-size:0.75rem;color:var(--text-muted);margin-bottom:1.25rem" data-users-assignment-help>Kullanıcının profilindeki ID kodunu yazın, bilgilerini görüp çalışma daveti gönderin.</p>
                <form class="form-grid" data-users-create-form>
                    <div class="field-wrap">
                        <label class="field-label">Kullanıcı ID</label>
                        <div style="display:flex;gap:0.55rem">
                            <input class="field-input" name="profile_code" placeholder="TD-000123" required>
                            <button class="button-secondary" type="button" data-users-code-search style="padding:0.55rem 0.85rem;flex-shrink:0">Bul</button>
                        </div>
                    </div>
                    <div data-users-candidate-card class="list-card" style="display:none;padding:0.9rem 1rem"></div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap">
                            <label class="field-label">Teklif Rolü</label>
                            <select class="field-select" name="role" data-users-role-select></select>
                        </div>
                        <div class="field-wrap" data-users-create-studio-wrap>
                            <label class="field-label">Stüdyo</label>
                            <select class="field-select" name="studio_id" data-users-create-studio></select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.65rem">
                        <button class="button-secondary" type="button" data-users-view-profile disabled style="justify-content:center">Profili Görüntüle</button>
                        <button class="button-primary" type="submit" data-users-send-invite disabled style="justify-content:center">İş Teklifi Yap</button>
                    </div>
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
    const lockedStudioLabel  = qs('[data-users-locked-studio]', root);
    const createStudioSelect = qs('[data-users-create-studio]', root);
    const createStudioWrap   = qs('[data-users-create-studio-wrap]', root);
    const assignmentHelp     = qs('[data-users-assignment-help]', root);
    const candidateCard      = qs('[data-users-candidate-card]', root);
    const searchButton       = qs('[data-users-code-search]', root);
    const viewProfileButton  = qs('[data-users-view-profile]', root);
    const sendInviteButton   = qs('[data-users-send-invite]', root);
    const listNode           = qs('[data-users-list]', root);
    const form               = qs('[data-users-create-form]', root);
    const roleSelect         = qs('[data-users-role-select]', root);
    let inviteCandidate = null;

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
        if (locksToOwnStudio && studios[0]?.id) {
            studioSelect.value = String(studios[0].id);
            if (createStudioSelect) createStudioSelect.value = String(studios[0].id);
        }
        if (lockedStudioLabel) {
            lockedStudioLabel.textContent = studios[0]?.name
                ? `Stüdyo: ${studios[0].name}`
                : 'Atanmış stüdyo bulunamadı';
        }
        return studios;
    };

    const updateCreateAssignmentMode = () => {
        if (!roleSelect || !createStudioWrap) return;
        createStudioWrap.style.display = locksToOwnStudio ? 'none' : '';
        if (assignmentHelp) {
            assignmentHelp.textContent = 'Kullanıcının profilindeki ID kodunu yazın, bilgilerini görüp çalışma daveti gönderin.';
        }
    };

    const renderInviteCandidate = (candidate) => {
        inviteCandidate = candidate;
        const inviteRoles = candidate?.can_invite_roles || [];
        if (roleSelect) {
            roleSelect.innerHTML = inviteRoles.map((role) =>
                `<option value="${role.value}">${escapeHtml(role.label)}</option>`
            ).join('');
        }
        if (candidateCard) {
            candidateCard.style.display = candidate ? '' : 'none';
            candidateCard.innerHTML = candidate ? `
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem">
                    <div style="min-width:0">
                        <div style="font-size:0.9rem;font-weight:700;color:var(--text-main)">${escapeHtml(candidate.name || 'İsimsiz kullanıcı')}</div>
                        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.15rem">${escapeHtml(candidate.profile_code || '')} · ${escapeHtml(candidate.profile_role_label || roleLabel(candidate.profile_role))}</div>
                        <div style="font-size:0.72rem;color:var(--text-subtle);margin-top:0.25rem">${escapeHtml(candidate.email || 'E-posta yok')}</div>
                        ${candidate.phone ? `<div style="font-size:0.72rem;color:var(--text-subtle);margin-top:0.15rem">${escapeHtml(candidate.phone)}</div>` : ''}
                    </div>
                    <span class="badge-pill ${inviteRoles.length ? 'badge-pill--success' : 'badge-pill--warning'}" style="font-size:0.62rem">${inviteRoles.length ? 'Davet edilebilir' : 'Uygun değil'}</span>
                </div>
                ${!inviteRoles.length ? `<div style="margin-top:0.7rem;font-size:0.72rem;color:var(--text-muted)">${candidate.current_studio?.name ? `${escapeHtml(candidate.current_studio.name)} stüdyosunda aktif çalışıyor.` : 'Bu kullanıcı seçilebilir çalışma rolüyle kayıtlı değil.'}</div>` : ''}
            ` : '';
        }
        if (viewProfileButton) viewProfileButton.disabled = !candidate;
        if (sendInviteButton) sendInviteButton.disabled = !candidate || inviteRoles.length === 0;
    };

    const searchInviteCandidate = async () => {
        const code = form?.querySelector('[name="profile_code"]')?.value?.trim();
        if (!code) throw new Error('Kullanıcı ID kodu girin.');
        const payload = await apiFetch(`/users/lookup-by-code/${encodeURIComponent(code)}`);
        renderInviteCandidate(payload.data);
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
                const canEdit = adminConfig.canManageUsers && adminConfig.isAdmin && canEditUserInfo(user);
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
                    <div style="margin-top:0.7rem;font-size:0.72rem;color:var(--text-subtle)">Kullanıcı bilgilerini yalnızca admin veya kullanıcının kendisi düzenleyebilir.</div>
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

    await loadStudios();
    updateCreateAssignmentMode();
    await renderUsers();

    studioSelect.addEventListener('change', () => handleAsync(renderUsers));
    qs('[data-users-refresh]', root)?.addEventListener('click', () => handleAsync(renderUsers));
    searchButton?.addEventListener('click', () => handleAsync(searchInviteCandidate));
    viewProfileButton?.addEventListener('click', () => {
        if (!inviteCandidate) return;
        window.alert([
            inviteCandidate.name || 'Kullanıcı',
            `ID: ${inviteCandidate.profile_code || '-'}`,
            `Rol: ${inviteCandidate.profile_role_label || roleLabel(inviteCandidate.profile_role)}`,
            `E-posta: ${inviteCandidate.email || '-'}`,
            `Telefon: ${inviteCandidate.phone || '-'}`,
            inviteCandidate.bio ? `Bio: ${inviteCandidate.bio}` : '',
        ].filter(Boolean).join('\n'));
    });

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            handleAsync(async () => {
                const data = Object.fromEntries(new FormData(form).entries());
                if (!inviteCandidate) throw new Error('Önce kullanıcı ID kodu ile kişiyi bulun.');
                await apiFetch(`/users/${inviteCandidate.id}/staff-invitations`, {
                    method: 'POST',
                    body: {
                        studio_id: data.studio_id,
                        role: data.role,
                    },
                });
                form.reset();
                if (createStudioSelect) createStudioSelect.value = studioSelect.value;
                renderInviteCandidate(null);
                showToast('İş teklifi kullanıcıya gönderildi.', 'success');
                await renderUsers();
            });
        });
    }
};

/* ── Randevular ─────────────────────────────────────────────── */

const renderAppointmentsPage = async (root) => {
    const locksToOwnStudio = adminConfig.isSupervisor;
    const title = isDriverRole() ? 'Transferler' : isArtistLikeRole() ? 'Atanan Biletler' : 'Randevu ve Bilet Yönetimi';
    const desc = isDriverRole()
        ? 'Pick up seçili transferler, müşteri bilgileri ve sürücü aksiyonları.'
        : isArtistLikeRole()
            ? 'Size atanan dövme/piercing biletleri ve tasarım randevuları.'
            : 'Tasarım işleri randevu, dövme/piercing işleri bilet olarak takip edilir.';

    root.innerHTML = `
        ${pageHeader('Randevu ve Bilet Akışı', title, desc, '<span class="badge-pill badge-pill--warning">Canlı Akış</span>')}
        <div style="display:grid;gap:1rem">
            <div class="panel-card">
                <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem">
                    ${canManageAppointmentRecords() ? `
                    <div class="field-wrap" data-appointments-studio-wrap style="flex:1;min-width:0;${locksToOwnStudio ? 'display:none' : ''}">
                        <label class="field-label">Stüdyo Seç</label>
                        <select class="field-select" data-appointments-studio-select></select>
                    </div>
                    ${locksToOwnStudio ? '<div class="badge-pill" data-appointments-locked-studio>Stüdyo yükleniyor...</div>' : ''}
                    ` : '<div></div>'}
                    <button class="button-secondary" data-appointments-refresh style="padding:0.55rem 0.85rem;font-size:0.78rem;flex-shrink:0">Yenile</button>
                </div>
                <div class="list-stack" data-appointments-list>${skeletonGrid(4)}</div>
            </div>
            ${canCreateAppointmentWeb() ? `
            <div class="form-shell">
                <div class="section-eyebrow" style="margin-bottom:0.4rem">Manuel Giriş</div>
                <div class="section-title" style="margin-bottom:1rem">Randevu Oluştur / Bilet Aç</div>
                <form class="form-grid" data-appointment-create-form enctype="multipart/form-data">
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap">
                            <label class="field-label">Stüdyo</label>
                            <select class="field-select" name="studio_id" data-appointment-create-studio ${locksToOwnStudio ? 'style="display:none"' : ''} required></select>
                            ${locksToOwnStudio ? '<div class="badge-pill" data-appointment-create-locked-studio>Stüdyo yükleniyor...</div>' : ''}
                        </div>
                        <div class="field-wrap">
                            <label class="field-label">Tür</label>
                            <select class="field-select" name="appointment_type">
                                <option value="designer">Randevu (Tasarım)</option>
                                <option value="tattoo">Bilet (Dövme/Piercing)</option>
                            </select>
                        </div>
                    </div>
                    ${ticketFieldsMarkup()}
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Ad</label><input class="field-input" name="customer[first_name]" required></div>
                        <div class="field-wrap"><label class="field-label">Soyad</label><input class="field-input" name="customer[last_name]" required></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap">
                            <label class="field-label">Ülke Kodu</label>
                            <select class="field-select" name="customer[phone_country_code]">
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
                        <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="customer[phone_number]" inputmode="tel"></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Otel</label><input class="field-input" name="customer[hotel_name]"></div>
                        <div class="field-wrap"><label class="field-label">Oda</label><input class="field-input" name="customer[room_number]"></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Tarih/Saat</label><input class="field-input" name="appointment_at" type="datetime-local" required></div>
                        <div class="field-wrap"><label class="field-label">Kişi</label><input class="field-input" name="pax" type="number" min="1" value="1" required></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap" data-price-field><label class="field-label">Fiyat <span style="color:var(--text-subtle)">(opsiyonel)</span></label><input class="field-input" name="price" type="number" min="0" step="0.01"></div>
                        <div class="field-wrap"><label class="field-label">Müşteri Fotoğrafı</label><input class="field-input" name="image" type="file" accept="image/*"></div>
                    </div>
                    <div class="field-wrap"><label class="field-label" data-appointment-images-label>Dövme Görselleri <span style="color:var(--text-subtle)">(en fazla 3)</span></label><input class="field-input" name="tattoo_images[]" type="file" accept="image/*" multiple></div>
                    <div class="field-wrap"><label class="field-label">Not</label><textarea class="field-input" name="notes" rows="3"></textarea></div>
                    <label data-pickup-field style="display:flex;align-items:center;gap:0.45rem;font-size:0.78rem;color:var(--text-muted)"><input type="checkbox" name="pickup_required" value="1"> Pick up gerekli</label>
                    <button class="button-primary" type="submit" style="justify-content:center">Kaydı Oluştur</button>
                </form>
            </div>
            ` : ''}
        </div>
    `;

    const studioSelect = qs('[data-appointments-studio-select]', root);
    const createStudioSelect = qs('[data-appointment-create-studio]', root);
    const lockedStudioLabel = qs('[data-appointments-locked-studio]', root);
    const createLockedStudioLabel = qs('[data-appointment-create-locked-studio]', root);
    const listNode = qs('[data-appointments-list]', root);
    const createAppointmentForm = qs('[data-appointment-create-form]', root);
    bindTicketFields(createAppointmentForm);
    bindDesignerAppointmentFields(createAppointmentForm);

    const loadStudios = async () => {
        if (!studioSelect && !createStudioSelect) return;
        const payload = await apiFetch('/studios/options');
        const studios = uniqueById(payload.data || []);
        if (studioSelect) {
            studioSelect.innerHTML = studios.map((s) => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        }
        if (createStudioSelect) {
            createStudioSelect.innerHTML = studios.map((s) => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        }
        if (locksToOwnStudio && studios[0]?.id) {
            if (studioSelect) studioSelect.value = String(studios[0].id);
            if (createStudioSelect) createStudioSelect.value = String(studios[0].id);
        }
        if (lockedStudioLabel) {
            lockedStudioLabel.textContent = studios[0]?.name
                ? `Stüdyo: ${studios[0].name}`
                : 'Atanmış stüdyo bulunamadı';
        }
        if (createLockedStudioLabel) {
            createLockedStudioLabel.textContent = studios[0]?.name
                ? `Stüdyo: ${studios[0].name}`
                : 'Atanmış stüdyo bulunamadı';
        }
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
            listNode.innerHTML = '<div class="empty-state">Kayıtları görüntülemek için bir stüdyo seçin.</div>';
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
                const metaLine = ticketMetaLine(apt);
                const ticketTimePending = apt.appointment_type === 'tattoo'
                    && apt.appointment_at
                    && new Date(apt.appointment_at).getTime() > Date.now();
                return `
                <article class="list-card animate-stagger-${(i % 3) + 1}" data-appointment-id="${apt.id}" data-studio-id="${studioId}" data-ticket-time-pending="${ticketTimePending ? '1' : '0'}" style="padding:0.85rem 1rem">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;margin-bottom:0.85rem">
                        <div style="display:flex;align-items:flex-start;gap:0.75rem;min-width:0">
                            ${renderImageThumb(apt)}
                            <div>
                                <div style="font-size:0.875rem;font-weight:600;color:var(--text-main)">${escapeHtml(limited ? 'Atanan bilet' : (customerName || 'İsimsiz'))}</div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">Otel/Yer: ${escapeHtml(limited ? (apt.studio?.name || 'Stüdyo') : (apt.customer?.hotel_name || apt.place || '—'))}</div>
                                <div style="margin-top:0.15rem;font-size:0.7rem;color:var(--text-subtle)">Oda: ${escapeHtml(limited ? '—' : (apt.customer?.room_number || '—'))}</div>
                                <div style="margin-top:0.15rem;font-size:0.7rem;color:var(--text-subtle)">Tarih: ${formatDateTime(apt.appointment_at)}</div>
                                <div style="margin-top:0.15rem;font-size:0.7rem;color:var(--text-subtle)">Stüdyo: ${escapeHtml(apt.studio?.name || 'Bağımsız')}</div>
                                ${metaLine ? `<div style="margin-top:0.15rem;font-size:0.7rem;color:var(--text-subtle)">${escapeHtml(metaLine)}</div>` : ''}
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.3rem;flex-shrink:0">
                            <span class="${statusClass(apt.status)}" style="font-size:0.65rem">${statusLabel(apt.status)}</span>
                            ${apt.appointment_type ? `<span class="badge-pill badge-pill--teal" style="font-size:0.6rem">${APPOINTMENT_TYPE_LABELS[apt.appointment_type] ?? apt.appointment_type}</span>` : ''}
                            ${apt.price !== null && apt.price !== undefined ? `<span class="badge-pill" style="font-size:0.6rem">${escapeHtml(apt.price)} €</span>` : ''}
                            ${apt.deposit_amount !== null && apt.deposit_amount !== undefined ? `<span class="badge-pill" style="font-size:0.6rem">Depozito ${escapeHtml(apt.deposit_amount)} €</span>` : ''}
                        </div>
                    </div>
                    ${canManageAppointmentRecords() ? `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.75rem">
                        <div class="field-wrap">
                            <label class="field-label">Durum</label>
                            <select class="field-select" data-appointment-status style="font-size:0.78rem;padding:0.42rem 0.65rem">
                                ${['confirmed','in_progress','completed','cancelled'].map((s) => `<option value="${s}" ${apt.status === s ? 'selected' : ''}>${statusLabel(s)}</option>`).join('')}
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
                            ${apt.appointment_type === 'tattoo' ? `<label style="display:flex;align-items:center;gap:0.35rem;font-size:0.72rem;color:var(--text-muted)"><input type="checkbox" data-appointment-pickup ${apt.pickup_required ? 'checked' : ''}> Pick up</label>` : ''}
                            <button class="button-secondary" data-appointment-save style="padding:0.4rem 0.75rem;font-size:0.75rem">Kaydet</button>
                        ` : ''}
                        ${isDriverRole() ? `
                            ${phone ? `<a href="tel:${escapeHtml(phone)}" class="button-secondary" style="padding:0.4rem 0.75rem;font-size:0.75rem">Müşteriyi Ara</a>` : ''}
                            <button class="button-secondary" data-driver-action="picked_up" style="padding:0.4rem 0.75rem;font-size:0.75rem">Aldım</button>
                            <button class="button-primary" data-driver-action="dropped_off" style="padding:0.4rem 0.75rem;font-size:0.75rem">Bıraktım</button>
                            <button class="button-ghost" data-driver-action="customer_no_show" style="padding:0.4rem 0.75rem;font-size:0.75rem">Müşteri Gelmedi</button>
                        ` : ''}
                        ${adminConfig.role === 'artist' && apt.appointment_type === 'tattoo' && !['completed', 'cancelled'].includes(apt.status) ? `
                            <form data-artist-complete-form title="${ticketTimePending ? 'Bilet zamanı gelmemiştir.' : ''}" style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
                                <input class="field-input" type="file" name="completed_tattoo_image" accept="image/*" required ${ticketTimePending ? 'disabled' : ''} style="max-width:240px;font-size:0.75rem;padding:0.45rem 0.65rem">
                                <button class="button-primary" type="submit" ${ticketTimePending ? 'disabled title="Bilet zamanı gelmemiştir."' : ''} style="padding:0.4rem 0.75rem;font-size:0.75rem;${ticketTimePending ? 'opacity:0.55;cursor:not-allowed' : ''}">Tamamla</button>
                            </form>
                        ` : ''}
                    </div>
                </article>
            `}).join('')
            : '<div class="empty-state">Bu kapsamda kayıt bulunmuyor.</div>';

        listNode.querySelectorAll('[data-appointment-save]').forEach((btn) => {
            btn.addEventListener('click', () => handleAsync(async () => {
                const card = btn.closest('[data-appointment-id]');
                const id = card?.getAttribute('data-appointment-id');
                const studioId = card?.getAttribute('data-studio-id');
                const type = qs('[data-appointment-type]', card)?.value || 'designer';
                await apiFetch(`/studios/${studioId}/appointments/${id}`, {
                    method: 'PATCH',
                    body: {
                        status: qs('[data-appointment-status]', card)?.value,
                        appointment_type: type,
                        pickup_required: type === 'designer' ? true : (qs('[data-appointment-pickup]', card)?.checked || false),
                    },
                });
                showToast('Kayıt güncellendi.', 'success');
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
                    if (card?.getAttribute('data-ticket-time-pending') === '1') {
                        showToast('Bilet zamanı gelmemiştir.', 'error');
                        return;
                    }
                    await apiFetch(`/appointments/${card?.getAttribute('data-appointment-id')}/artist-complete`, {
                        method: 'POST',
                        body: new FormData(form),
                    });
                    showToast('Bilet tamamlandı.', 'success');
                    await renderAppointments();
                });
            });
        });
    };

    await loadStudios();
    await renderAppointments();

    studioSelect?.addEventListener('change', () => handleAsync(renderAppointments));
    qs('[data-appointments-refresh]', root)?.addEventListener('click', () => handleAsync(renderAppointments));
    createAppointmentForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        handleAsync(async () => {
            const form = e.target;
            validateTicketFields(form);
            const tattooFiles = form.querySelector('input[name="tattoo_images[]"]')?.files;
            const isTicket = form.querySelector('[name="appointment_type"]')?.value === 'tattoo';
            if (isTicket && tattooFiles && tattooFiles.length > 3) {
                throw new Error('En fazla 3 dövme görseli ekleyebilirsiniz.');
            }
            const studioId = form.querySelector('[name="studio_id"]')?.value;
            if (!studioId) throw new Error('Stüdyo seçin.');
            const formData = new FormData(form);
            formData.delete('studio_id');
            if (formData.get('appointment_type') === 'designer') {
                formData.delete('price');
                formData.set('pickup_required', '1');
            }
            if (formData.get('appointment_at')) {
                formData.set('appointment_at', new Date(formData.get('appointment_at')).toISOString());
            }
            await apiFetch(`/studios/${studioId}/appointments`, { method: 'POST', body: formData });
            showToast((formData.get('appointment_type') === 'tattoo' ? 'Bilet açıldı.' : 'Randevu oluşturuldu.'), 'success');
            form.reset();
            form.querySelector('[name="appointment_type"]')?.dispatchEvent(new Event('change'));
            await renderAppointments();
        });
    });
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
            ${adminConfig.canManageShops ? `
            <div class="form-shell" data-studio-create-shell style="align-self:start">
                <div class="section-eyebrow" style="margin-bottom:0.4rem">Yeni Stüdyo</div>
                <div class="section-title" style="margin-bottom:1.25rem">Stüdyo Oluştur</div>
                <form class="form-grid" data-studio-create-form>
                    <div class="field-wrap">
                        <label class="field-label">Bağlı Şirket</label>
                        <select class="field-select" name="company_id" data-studio-create-company required></select>
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
    const [payload, companiesPayload] = await Promise.all([
        apiFetch('/studios/overview'),
        adminConfig.canManageShops ? apiFetch('/companies') : Promise.resolve({ data: [] }),
    ]);
    const studios = payload.data || [];
    const companies = uniqueById(companiesPayload.data || []);
    const createCompanySelect = qs('[data-studio-create-company]', root);
    if (createCompanySelect) {
        createCompanySelect.innerHTML = companies.length
            ? companies.map((company) => `<option value="${company.id}">${escapeHtml(company.name)}</option>`).join('')
            : '<option value="">Önce şirket oluşturun</option>';
        createCompanySelect.disabled = companies.length === 0;
    }

    grid.innerHTML = studios.length
        ? studios.map((studio, i) => `
            <article class="data-card animate-stagger-${(i % 3) + 1}">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;margin-bottom:1rem">
                    <div>
                        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.10em;color:var(--text-muted);margin-bottom:0.3rem">${escapeHtml(studio.company?.name || 'Şirket bilgisi yok')}</div>
                        <div class="section-title">${escapeHtml(studio.name)}</div>
                    </div>
                    <span class="badge-pill badge-pill--info" style="font-size:0.65rem;flex-shrink:0">${studio.appointments_count} randevu</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr;gap:0.6rem;margin-bottom:1.25rem">
                    ${statBlock('Konum', escapeHtml(studio.location || '—'))}
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
            if (!data.company_id) throw new Error('Stüdyo oluşturmak için önce şirket seçin.');
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
        ${pageHeader('Şirket Yönetimi', 'Şirket Ağı', 'Şirket bazlı stüdyo ve randevu verilerini anlık görün.', '<span class="badge-pill badge-pill--info">Platform Yönetimi</span>')}
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
                    <div class="field-wrap"><label class="field-label">Max Stüdyo <span style="color:var(--text-subtle)">(0=∞)</span></label><input class="field-input" type="number" min="0" name="max_studio_count" value="0"></div>
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
                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.5rem;margin-bottom:1rem">
                            <div class="stat-block">
                                <div class="stat-label">Randevu</div>
                                <div style="margin-top:0.4rem;font-size:1.35rem;font-weight:800;letter-spacing:-0.02em;color:var(--text-main)" data-counter="${company.appointment_count}">0</div>
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
                                <div class="field-wrap"><label class="field-label">Max Stüdyo</label><input class="field-input" type="number" min="0" name="max_studio_count" value="${company.max_studio_count}"></div>
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
    const initialArtistId = params.get('artist_id');

    root.innerHTML = `
        ${pageHeader('Talep Yönetimi', 'Randevu / Bilet Talepleri', 'Tasarım talepleri randevuya, dövme/piercing talepleri bilete dönüşür.', '<span class="badge-pill badge-pill--teal">Talep Akışı</span>')}
        <div class="form-shell" style="margin-bottom:1rem">
            <div class="section-eyebrow" style="margin-bottom:0.4rem">Yeni Talep</div>
            <div class="section-title" style="margin-bottom:1rem">Stüdyoya Talep Gönder</div>
            <form class="form-grid" data-request-create-form enctype="multipart/form-data">
                <div class="form-grid form-grid--split">
                    <div class="field-wrap">
                        <label class="field-label">Stüdyo</label>
                        <select class="field-select" name="studio_id" data-request-studio-select ${initialArtistId ? '' : 'required'}></select>
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Tür</label>
                        <select class="field-select" name="type" required>
                            <option value="designer">Randevu (Tasarım)</option>
                            <option value="tattoo">Bilet (Dövme/Piercing)</option>
                        </select>
                    </div>
                </div>
                ${ticketFieldsMarkup()}
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
                    <div class="field-wrap"><label class="field-label" data-appointment-images-label>Dövme Görselleri <span style="color:var(--text-subtle)">(en fazla 3)</span></label><input class="field-input" name="tattoo_images[]" type="file" accept="image/*" multiple></div>
                </div>
                <div class="field-wrap"><label class="field-label">Not</label><textarea class="field-input" name="notes" rows="3"></textarea></div>
                <label data-pickup-field style="display:flex;align-items:center;gap:0.45rem;font-size:0.78rem;color:var(--text-muted)">
                    <input type="checkbox" name="pickup_required" value="1">
                    Pick up gerekli
                </label>
                ${initialArtistId ? `<input type="hidden" name="artist_id" value="${escapeHtml(initialArtistId)}">` : ''}
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
    bindTicketFields(createForm, 'type');
    bindDesignerAppointmentFields(createForm, 'type');

    const loadRequestStudios = async () => {
        const payload = await apiFetch('/public/studios').catch(() => ({ data: [] }));
        const studios = payload.data || [];
        requestStudioSelect.innerHTML = `
            ${initialArtistId ? '<option value="">Artist / freelancer hedefli</option>' : ''}
            ${studios.map((studio) =>
                `<option value="${studio.id}" ${String(studio.id) === String(params.get('studio_id')) ? 'selected' : ''}>${escapeHtml(studio.name)}</option>`
            ).join('')}
        `;
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
                const metaLine = ticketMetaLine(request);
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
                                ${metaLine ? `<div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-subtle)">${escapeHtml(metaLine)}</div>` : ''}
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.35rem">
                            <span class="${requestStatusClass(request.status)}" style="font-size:0.65rem">${requestStatusLabel(request.status)}</span>
                            <span class="badge-pill badge-pill--teal" style="font-size:0.6rem">${APPOINTMENT_TYPE_LABELS[request.request_type] || request.request_type}</span>
                            ${request.price !== null && request.price !== undefined ? `<span class="badge-pill" style="font-size:0.6rem">${escapeHtml(request.price)} €</span>` : ''}
                            ${request.deposit_amount !== null && request.deposit_amount !== undefined ? `<span class="badge-pill" style="font-size:0.6rem">Depozito ${escapeHtml(request.deposit_amount)} €</span>` : ''}
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
                            ${request.request_type === 'tattoo' ? `
                                <div class="field-wrap" style="max-width:160px">
                                    <label class="field-label">Depozito</label>
                                    <input class="field-input" data-request-deposit type="number" min="0" step="0.01" value="${escapeHtml(request.deposit_amount ?? '')}">
                                </div>
                                <div class="field-wrap" style="max-width:190px">
                                    <label class="field-label">Ödeme</label>
                                    <select class="field-select" data-request-payment>
                                        ${Object.entries(PAYMENT_METHOD_LABELS).map(([value, label]) => `<option value="${value}" ${request.payment_method === value ? 'selected' : ''}>${escapeHtml(label)}</option>`).join('')}
                                    </select>
                                </div>
                            ` : ''}
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
                const body = { price };
                const depositAmount = qs('[data-request-deposit]', card)?.value;
                const paymentMethod = qs('[data-request-payment]', card)?.value;
                if (depositAmount !== undefined && depositAmount !== '') body.deposit_amount = depositAmount;
                if (paymentMethod) body.payment_method = paymentMethod;
                await apiFetch(`/appointment-requests/${card?.getAttribute('data-request-id')}/accept`, {
                    method: 'PATCH',
                    body,
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
            validateTicketFields(createForm, 'type');
            const tattooFiles = createForm.querySelector('input[name="tattoo_images[]"]')?.files;
            const isTicket = createForm.querySelector('[name="type"]')?.value === 'tattoo';
            if (isTicket && tattooFiles && tattooFiles.length > 3) {
                throw new Error('En fazla 3 dövme görseli ekleyebilirsiniz.');
            }
            const formData = new FormData(createForm);
            if (formData.get('type') === 'designer') {
                formData.delete('price');
                formData.set('pickup_required', '1');
            }
            const normalized = new FormData();
            for (const [key, value] of formData.entries()) {
                normalized.append(key === 'tattoo_images[]' ? 'tattoo_images[]' : key, value);
            }
            await apiFetch('/appointments/request', { method: 'POST', body: normalized });
            showToast('Talep gönderildi.', 'success');
            createForm.reset();
            createForm.querySelector('[name="type"]')?.dispatchEvent(new Event('change'));
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

/* ── Keşfet / Public Detaylar ───────────────────────────────── */

const mediaGrid = (items = [], alt = 'Görsel') => `
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:0.65rem">
        ${items.map((item) => {
            const src = typeof item === 'string' ? item : (item.image_path || item.image_url || item.url);
            if (!src) return '';
            return `<a href="${escapeHtml(src)}" target="_blank" rel="noopener noreferrer"><img src="${escapeHtml(src)}" alt="${escapeHtml(alt)}" style="width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:0.7rem;border:1px solid var(--border)"></a>`;
        }).join('') || '<div class="empty-state" style="padding:1rem;border:none">Görsel bulunmuyor.</div>'}
    </div>
`;

const renderDiscoveryPage = async (root) => {
    root.innerHTML = `
        ${pageHeader('Keşfet', 'Stüdyolar ve Freelancerlar', 'Mobil keşif akışındaki stüdyo, artist ve tasarımcıları webde de görüntüleyin.', '<span class="badge-pill badge-pill--teal">Sosyal Akış</span>')}
        <div class="panel-card">
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem">
                <button class="button-primary" data-discovery-tab="studios" style="padding:0.5rem 0.85rem;font-size:0.78rem">Stüdyolar</button>
                <button class="button-secondary" data-discovery-tab="artists" style="padding:0.5rem 0.85rem;font-size:0.78rem">Artist & Tasarımcı</button>
            </div>
            <div data-discovery-content>${skeletonGrid(6)}</div>
        </div>
    `;

    const content = qs('[data-discovery-content]', root);
    let activeTab = 'studios';

    const setActiveTab = () => {
        root.querySelectorAll('[data-discovery-tab]').forEach((btn) => {
            const active = btn.getAttribute('data-discovery-tab') === activeTab;
            btn.classList.toggle('button-primary', active);
            btn.classList.toggle('button-secondary', !active);
        });
    };

    const renderStudios = async () => {
        content.innerHTML = skeletonGrid(6);
        const payload = await apiFetch('/public/studios');
        const studios = payload.data || [];
        content.innerHTML = `
            <div class="data-grid">
                ${studios.map((studio, i) => {
                    const portfolio = studio.portfolio || studio.gallery_images || [];
                    return `
                    <article class="data-card animate-stagger-${(i % 3) + 1}">
                        ${portfolio[0] ? `<img src="${escapeHtml(portfolio[0])}" alt="${escapeHtml(studio.name)}" style="width:100%;height:150px;object-fit:cover;border-radius:0.75rem;border:1px solid var(--border);margin-bottom:0.85rem">` : ''}
                        <div class="section-title">${escapeHtml(studio.name)}</div>
                        <div style="margin-top:0.25rem;font-size:0.75rem;color:var(--text-muted)">${escapeHtml(studio.location || 'Konum yok')}</div>
                        <div style="margin-top:0.75rem;font-size:0.78rem;color:var(--text-muted);line-height:1.55;min-height:2.4rem">${escapeHtml(studio.about || 'Açıklama eklenmemiş.')}</div>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-top:1rem;padding-top:0.85rem;border-top:1px solid var(--border)">
                            <span class="badge-pill" style="font-size:0.6rem">${escapeHtml(studio.company?.name || 'Stüdyo')}</span>
                            <a class="button-secondary" href="/admin/discovery/studios/${studio.id}" style="padding:0.45rem 0.75rem;font-size:0.74rem">Detay</a>
                        </div>
                    </article>
                `}).join('') || '<div class="empty-state">Stüdyo bulunmuyor.</div>'}
            </div>
        `;
    };

    const renderArtists = async () => {
        content.innerHTML = skeletonGrid(6);
        const payload = await apiFetch('/public/artists');
        const artists = payload.data || [];
        content.innerHTML = `
            <div class="data-grid">
                ${artists.map((artist, i) => {
                    const preview = artist.portfolio_preview || artist.portfolio || [];
                    const firstImage = preview[0]?.image_path || preview[0]?.image_url || preview[0];
                    return `
                    <article class="data-card animate-stagger-${(i % 3) + 1}">
                        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.85rem">
                            ${artist.profile_image ? `<img src="${escapeHtml(artist.profile_image)}" alt="${escapeHtml(artist.name)}" style="width:52px;height:52px;object-fit:cover;border-radius:50%;border:1px solid var(--border)">` : '<div style="width:52px;height:52px;border-radius:50%;background:var(--surface-soft);border:1px solid var(--border)"></div>'}
                            <div>
                                <div class="section-title">${escapeHtml(artist.name)}</div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">${escapeHtml(artist.role_label || roleLabel(artist.role))}</div>
                            </div>
                        </div>
                        ${firstImage ? `<img src="${escapeHtml(firstImage)}" alt="Portfolyo" style="width:100%;height:140px;object-fit:cover;border-radius:0.75rem;border:1px solid var(--border);margin-bottom:0.75rem">` : ''}
                        <div style="font-size:0.78rem;color:var(--text-muted);line-height:1.55;min-height:2.4rem">${escapeHtml(artist.bio || 'Biyografi eklenmemiş.')}</div>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-top:1rem;padding-top:0.85rem;border-top:1px solid var(--border)">
                            <span class="badge-pill" style="font-size:0.6rem">${artist.is_freelancer ? 'Freelancer' : 'Stüdyo'}</span>
                            <a class="button-secondary" href="/admin/discovery/artists/${artist.id}" style="padding:0.45rem 0.75rem;font-size:0.74rem">Profil</a>
                        </div>
                    </article>
                `}).join('') || '<div class="empty-state">Artist veya tasarımcı bulunmuyor.</div>'}
            </div>
        `;
    };

    const render = async () => {
        setActiveTab();
        if (activeTab === 'artists') await renderArtists();
        else await renderStudios();
    };

    root.querySelectorAll('[data-discovery-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            activeTab = button.getAttribute('data-discovery-tab') || 'studios';
            handleAsync(render);
        });
    });

    await render();
};

const renderPublicStudioDetailPage = async (root) => {
    const studioId = root.getAttribute('data-studio-id');
    const [detailPayload, reviewsPayload] = await Promise.all([
        apiFetch(`/public/studios/${studioId}`),
        apiFetch(`/public/studios/${studioId}/reviews`).catch(() => ({ data: { items: [] } })),
    ]);
    const studio = detailPayload.data || {};
    const reviews = reviewsPayload.data?.items || reviewsPayload.data || [];

    root.innerHTML = `
        ${pageHeader('Stüdyo', escapeHtml(studio.name || 'Stüdyo Detayı'), escapeHtml(studio.location || ''), '<span class="badge-pill badge-pill--teal">Public Profil</span>')}
        <div style="display:grid;gap:1rem;grid-template-columns:1fr 0.9fr">
            <div class="panel-card">
                <div class="section-title" style="margin-bottom:0.75rem">Portfolyo</div>
                ${mediaGrid(studio.portfolio || studio.aggregated_gallery || studio.gallery_images || [], 'Stüdyo portfolyo')}
            </div>
            <div class="panel-card">
                <div class="section-title" style="margin-bottom:0.75rem">Bilgiler</div>
                <div class="detail-grid">
                    <div class="detail-row"><span class="detail-label">Şirket</span><span class="detail-value">${escapeHtml(studio.company?.name || '—')}</span></div>
                    <div class="detail-row"><span class="detail-label">Puan</span><span class="detail-value">${escapeHtml(studio.rating || '—')}</span></div>
                    <div class="detail-row"><span class="detail-label">Tamamlanan</span><span class="detail-value">${escapeHtml(studio.appointment_stats?.completed ?? 0)}</span></div>
                    <div class="detail-row"><span class="detail-label">İptal</span><span class="detail-value">${escapeHtml(studio.appointment_stats?.cancelled ?? 0)}</span></div>
                </div>
                <a href="/admin/appointment-requests?studio_id=${encodeURIComponent(studio.id)}" class="button-primary" style="justify-content:center;margin-top:1rem">Talep Gönder</a>
            </div>
        </div>
        <div class="panel-card" style="margin-top:1rem">
            <div class="section-title" style="margin-bottom:1rem">Çalışanlar</div>
            <div class="data-grid">
                ${(studio.staff || []).map((staff) => `
                    <a class="list-card" href="/admin/discovery/artists/${staff.id}" style="text-decoration:none;padding:0.75rem 0.85rem">
                        <div style="display:flex;align-items:center;gap:0.7rem">
                            ${staff.profile_image ? `<img src="${escapeHtml(staff.profile_image)}" alt="${escapeHtml(staff.name)}" style="width:44px;height:44px;object-fit:cover;border-radius:50%">` : '<div style="width:44px;height:44px;border-radius:50%;background:var(--surface-soft)"></div>'}
                            <div><div style="font-weight:650;color:var(--text-main)">${escapeHtml(staff.name)}</div><div style="font-size:0.72rem;color:var(--text-muted)">${escapeHtml(staff.role_label || roleLabel(staff.role))}</div></div>
                        </div>
                    </a>
                `).join('') || '<div class="empty-state">Çalışan bulunmuyor.</div>'}
            </div>
        </div>
        <div class="panel-card" style="margin-top:1rem">
            <div class="section-title" style="margin-bottom:1rem">Yorumlar</div>
            <div class="list-stack">
                ${reviews.map((review) => `
                    <div class="list-card" style="padding:0.75rem 0.85rem">
                        <div style="display:flex;justify-content:space-between;gap:1rem"><strong>${escapeHtml(review.user?.name || review.reviewer?.name || 'Kullanıcı')}</strong><span class="badge-pill">${escapeHtml(review.rating || '')}/5</span></div>
                        <div style="margin-top:0.4rem;font-size:0.78rem;color:var(--text-muted)">${escapeHtml(review.comment || review.body || '')}</div>
                        ${review.image_path ? `<a href="${escapeHtml(review.image_path)}" target="_blank" rel="noopener noreferrer"><img src="${escapeHtml(review.image_path)}" alt="Yorum görseli" style="margin-top:0.5rem;width:90px;height:90px;object-fit:cover;border-radius:0.6rem"></a>` : ''}
                    </div>
                `).join('') || '<div class="empty-state">Yorum bulunmuyor.</div>'}
            </div>
        </div>
    `;
};

const renderPublicArtistDetailPage = async (root) => {
    const artistId = root.getAttribute('data-artist-id');
    const [detailPayload, reviewsPayload] = await Promise.all([
        apiFetch(`/public/artists/${artistId}`),
        apiFetch(`/public/artists/${artistId}/reviews`).catch(() => ({ data: { items: [] } })),
    ]);
    const artist = detailPayload.data || {};
    const reviews = reviewsPayload.data?.items || reviewsPayload.data || [];

    root.innerHTML = `
        ${pageHeader('Artist', escapeHtml(artist.name || 'Profil'), escapeHtml(artist.bio || ''), `<span class="badge-pill badge-pill--success">${escapeHtml(artist.role_label || roleLabel(artist.role))}</span>`)}
        <div style="display:grid;gap:1rem;grid-template-columns:0.9fr 1.1fr">
            <div class="panel-card">
                ${artist.profile_image ? `<img src="${escapeHtml(artist.profile_image)}" alt="${escapeHtml(artist.name)}" style="width:120px;height:120px;object-fit:cover;border-radius:50%;border:1px solid var(--border);margin-bottom:1rem">` : ''}
                <div class="detail-grid">
                    <div class="detail-row"><span class="detail-label">Çalışma</span><span class="detail-value">${artist.is_freelancer ? 'Freelancer' : 'Stüdyo'}</span></div>
                    <div class="detail-row"><span class="detail-label">Puan</span><span class="detail-value">${escapeHtml(artist.rating || '—')}</span></div>
                    <div class="detail-row"><span class="detail-label">Tamamlanan</span><span class="detail-value">${escapeHtml(artist.appointment_stats?.completed ?? 0)}</span></div>
                    <div class="detail-row"><span class="detail-label">İptal</span><span class="detail-value">${escapeHtml(artist.appointment_stats?.cancelled ?? 0)}</span></div>
                </div>
                <a href="/admin/appointment-requests?artist_id=${encodeURIComponent(artist.id)}" class="button-primary" style="justify-content:center;margin-top:1rem">Talep Gönder</a>
            </div>
            <div class="panel-card">
                <div class="section-title" style="margin-bottom:0.75rem">Portfolyo</div>
                ${mediaGrid(artist.portfolio || [], 'Artist portfolyo')}
            </div>
        </div>
        <div class="panel-card" style="margin-top:1rem">
            <div class="section-title" style="margin-bottom:1rem">Yorumlar</div>
            <div class="list-stack">
                ${reviews.map((review) => `
                    <div class="list-card" style="padding:0.75rem 0.85rem">
                        <div style="display:flex;justify-content:space-between;gap:1rem"><strong>${escapeHtml(review.user?.name || review.reviewer?.name || 'Kullanıcı')}</strong><span class="badge-pill">${escapeHtml(review.rating || '')}/5</span></div>
                        <div style="margin-top:0.4rem;font-size:0.78rem;color:var(--text-muted)">${escapeHtml(review.comment || review.body || '')}</div>
                        ${review.image_path ? `<a href="${escapeHtml(review.image_path)}" target="_blank" rel="noopener noreferrer"><img src="${escapeHtml(review.image_path)}" alt="Yorum görseli" style="margin-top:0.5rem;width:90px;height:90px;object-fit:cover;border-radius:0.6rem"></a>` : ''}
                    </div>
                `).join('') || '<div class="empty-state">Yorum bulunmuyor.</div>'}
            </div>
        </div>
    `;
};

/* ── Profil / Portfolyo / Ayarlar ───────────────────────────── */

const renderProfilePage = async (root) => {
    const payload = await apiFetch('/profile');
    const profile = payload.data || {};
    const portfolio = profile.portfolio || [];
    const canDeleteAccount = !['admin', 'yonetici', 'supervisor'].includes(adminConfig.role);

    root.innerHTML = `
        ${pageHeader('Hesap', 'Profil', 'Profil bilgileri, portfolyo ve hesap işlemleri.', '<span class="badge-pill badge-pill--info">Web Profil</span>')}
        <div style="display:grid;gap:1rem;grid-template-columns:1fr 1fr">
            <div class="form-shell">
                <div class="section-title" style="margin-bottom:1rem">Profil Bilgileri</div>
                <form class="form-grid" data-profile-form>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Ad</label><input class="field-input" name="name" value="${escapeHtml((profile.name || '').split(' ')[0] || '')}"></div>
                        <div class="field-wrap"><label class="field-label">Soyad</label><input class="field-input" name="surname" value="${escapeHtml((profile.name || '').split(' ').slice(1).join(' '))}"></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">E-posta</label><input class="field-input" name="email" type="email" value="${escapeHtml(profile.email || '')}"></div>
                        <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="phone" value="${escapeHtml(profile.phone || '')}"></div>
                    </div>
                    <div class="field-wrap"><label class="field-label">Biyografi</label><textarea class="field-input" name="bio" rows="3">${escapeHtml(profile.bio || '')}</textarea></div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location" value="${escapeHtml(profile.location || '')}"></div>
                        <div class="field-wrap"><label class="field-label">Durum</label><select class="field-select" name="status">${['working','break','transfer'].map((s) => `<option value="${s}" ${profile.status === s ? 'selected' : ''}>${statusLabel(s)}</option>`).join('')}</select></div>
                    </div>
                    <button class="button-primary" type="submit" style="justify-content:center">Profili Güncelle</button>
                </form>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:1rem">
                    <a class="button-secondary" href="/admin/profile/appointments" style="padding:0.5rem 0.85rem;font-size:0.78rem">Randevularım</a>
                    ${canDeleteAccount ? '<button class="button-ghost" data-delete-account style="padding:0.5rem 0.85rem;font-size:0.78rem">Hesabımı Sil</button>' : ''}
                </div>
            </div>
            <div class="panel-card">
                <div class="section-title" style="margin-bottom:1rem">Portfolyo</div>
                ${mediaGrid(portfolio, 'Portfolyo')}
                <form class="form-grid" data-portfolio-form style="margin-top:1rem">
                    <div class="field-wrap"><label class="field-label">Başlık</label><input class="field-input" name="title" required></div>
                    <div class="field-wrap"><label class="field-label">Kategori</label><input class="field-input" name="category"></div>
                    <div class="field-wrap"><label class="field-label">Görsel</label><input class="field-input" name="image" type="file" accept="image/*"></div>
                    <div class="field-wrap"><label class="field-label">Açıklama</label><textarea class="field-input" name="description" rows="2"></textarea></div>
                    <button class="button-secondary" type="submit" style="justify-content:center">Portfolyoya Ekle</button>
                </form>
            </div>
        </div>
    `;

    qs('[data-profile-form]', root)?.addEventListener('submit', (e) => {
        e.preventDefault();
        handleAsync(async () => {
            const body = Object.fromEntries(new FormData(e.target).entries());
            await apiFetch('/profile', { method: 'PATCH', body });
            showToast('Profil güncellendi.', 'success');
            await renderProfilePage(root);
        });
    });

    qs('[data-portfolio-form]', root)?.addEventListener('submit', (e) => {
        e.preventDefault();
        handleAsync(async () => {
            await apiFetch('/me/portfolio/items', { method: 'POST', body: new FormData(e.target) });
            showToast('Portfolyo eklendi.', 'success');
            await renderProfilePage(root);
        });
    });

    qs('[data-delete-account]', root)?.addEventListener('click', () => {
        if (!confirm('Hesabınızı kalıcı olarak silmek istiyor musunuz?')) return;
        handleAsync(async () => {
            await apiFetch('/me', { method: 'DELETE' });
            window.location.href = '/admin/login';
        });
    });
};

const renderProfileAppointmentsPage = async (root) => {
    const canViewManagedHistory = ['admin', 'yonetici'].includes(adminConfig.role);
    root.innerHTML = `
        ${pageHeader('Profil', canViewManagedHistory ? 'Geçmiş Randevu / Biletler' : 'Randevu ve Biletlerim', canViewManagedHistory ? 'Yönetim kapsamındaki tamamlanan ve iptal edilen kayıtları filtrelerle inceleyin.' : 'Tarih, durum ve tür filtresiyle kişisel kayıt geçmişi.', '<span class="badge-pill badge-pill--warning">Arşiv</span>')}
        <div class="panel-card">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.65rem;margin-bottom:1rem">
                <select class="field-select" data-pa-status><option value="">Tüm Geçmiş</option><option value="completed">Tamamlandı</option><option value="cancelled">İptal</option></select>
                <select class="field-select" data-pa-type><option value="">Tüm Türler</option><option value="designer">Randevu</option><option value="tattoo">Bilet</option></select>
                <input class="field-input" data-pa-date-from type="date">
                <input class="field-input" data-pa-date-to type="date">
                ${canViewManagedHistory ? `
                    <select class="field-select" data-pa-company><option value="">Tüm Şirketler</option></select>
                    <select class="field-select" data-pa-studio><option value="">Tüm Stüdyolar</option></select>
                ` : ''}
                <input class="field-input" data-pa-search placeholder="Ara">
            </div>
            <div data-profile-appointments-summary style="margin-bottom:1rem"></div>
            <div class="list-stack" data-profile-appointments-list>${skeletonGrid(4)}</div>
        </div>
    `;

    const listNode = qs('[data-profile-appointments-list]', root);
    const summaryNode = qs('[data-profile-appointments-summary]', root);
    const companySelect = qs('[data-pa-company]', root);
    const studioSelect = qs('[data-pa-studio]', root);
    let studios = [];

    if (canViewManagedHistory) {
        const [companiesPayload, studiosPayload] = await Promise.all([
            apiFetch('/companies').catch(() => ({ data: [] })),
            apiFetch('/studios/overview').catch(() => ({ data: [] })),
        ]);
        const companies = companiesPayload.data || [];
        studios = uniqueById(studiosPayload.data || []);
        if (companySelect) {
            companySelect.innerHTML = '<option value="">Tüm Şirketler</option>' + companies.map((company) =>
                `<option value="${company.id}">${escapeHtml(company.name || 'Şirket')}</option>`
            ).join('');
        }
        if (studioSelect) {
            studioSelect.innerHTML = '<option value="">Tüm Stüdyolar</option>' + studios.map((studio) =>
                `<option value="${studio.id}" data-company-id="${studio.company?.id || studio.company_id || ''}">${escapeHtml(studio.name || 'Stüdyo')}</option>`
            ).join('');
        }
    }

    const renderSummary = (rows) => {
        const completed = rows.filter((item) => item.status === 'completed').length;
        const cancelled = rows.filter((item) => item.status === 'cancelled').length;
        const appointments = rows.filter((item) => item.appointment_type === 'designer' || item.request_type === 'designer').length;
        const tickets = rows.filter((item) => item.appointment_type === 'tattoo' || item.request_type === 'tattoo').length;
        const total = completed + cancelled;
        const completedRate = total ? Math.round((completed / total) * 100) : 0;
        summaryNode.innerHTML = `
            <div style="display:flex;align-items:center;gap:1rem;padding:1rem;border:1px solid var(--border);border-radius:1rem;background:var(--surface-soft)">
                <div style="width:92px;height:92px;border-radius:50%;background:${total ? `conic-gradient(var(--success) 0 ${completedRate}%, var(--danger) ${completedRate}% 100%)` : 'var(--surface)'};display:grid;place-items:center;box-shadow:inset 0 0 0 13px var(--surface)">
                    <div style="text-align:center">
                        <div style="font-size:1.35rem;font-weight:850;color:var(--text-main)">${total}</div>
                        <div style="font-size:0.66rem;font-weight:750;color:var(--text-muted)">kayıt</div>
                    </div>
                </div>
                <div style="min-width:0;flex:1">
                    <div class="section-title" style="margin-bottom:0.65rem">Geçmiş Özeti</div>
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                        <span class="badge-pill badge-pill--success">${completed} Yapılan</span>
                        <span class="badge-pill badge-pill--danger">${cancelled} İptal</span>
                        <span class="badge-pill badge-pill--teal">${appointments} Randevu</span>
                        <span class="badge-pill badge-pill--purple">${tickets} Bilet</span>
                    </div>
                </div>
            </div>
        `;
    };

    const load = async () => {
        listNode.innerHTML = skeletonGrid(4);
        const status = qs('[data-pa-status]', root).value;
        const type = qs('[data-pa-type]', root).value;
        const dateFrom = qs('[data-pa-date-from]', root).value;
        const dateTo = qs('[data-pa-date-to]', root).value;
        const selectedCompany = qs('[data-pa-company]', root)?.value || '';
        const selectedStudio = qs('[data-pa-studio]', root)?.value || '';
        const params = new URLSearchParams();
        if (canViewManagedHistory) {
            if (status) params.set('status', status);
            if (type) params.set('appointment_type', type);
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);
            if (selectedCompany) params.set('company_id', selectedCompany);
            if (selectedStudio) params.set('studio_id', selectedStudio);
        }
        const endpoint = canViewManagedHistory
            ? `/admin/appointments${params.toString() ? `?${params.toString()}` : ''}`
            : isDriverRole()
                ? '/my-appointments'
                : isArtistLikeRole()
                    ? '/my-artist-appointments'
                    : '/appointment-requests?direction=outgoing';
        const payload = await apiFetch(endpoint);
        const raw = payload.data || [];
        const rows = raw.filter((item) => {
            const search = qs('[data-pa-search]', root).value.toLowerCase().trim();
            const dateValue = item.appointment_at || item.requested_at;
            const haystack = JSON.stringify(item).toLowerCase();
            const isHistory = item.status === 'completed' || item.status === 'cancelled';
            return isHistory
                && (!status || item.status === status)
                && (!type || item.appointment_type === type || item.request_type === type)
                && (!dateFrom || (dateValue && dateValue.slice(0, 10) >= dateFrom))
                && (!dateTo || (dateValue && dateValue.slice(0, 10) <= dateTo))
                && (!search || haystack.includes(search));
        });
        renderSummary(rows);
        listNode.innerHTML = rows.map((item) => `
            <a class="list-card" href="${item.appointment_id || item.appointment_at ? `/admin/appointments/${item.appointment_id || item.id}` : `/admin/appointment-requests?request_id=${item.id}`}" style="text-decoration:none;padding:0.85rem 1rem">
                <div style="display:flex;justify-content:space-between;gap:1rem">
                    <div>
                        <div style="font-weight:700;color:var(--text-main)">${escapeHtml(`${item.customer?.first_name || item.first_name || ''} ${item.customer?.last_name || item.last_name || ''}`.trim() || 'Kayıt')}</div>
                        <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">${formatDateTime(item.appointment_at || item.requested_at)} · ${escapeHtml(item.studio?.name || '—')}</div>
                    </div>
                    <span class="${statusClass(item.status)}" style="font-size:0.65rem">${statusLabel(item.status) || requestStatusLabel(item.status)}</span>
                </div>
            </a>
        `).join('') || '<div class="empty-state">Kayıt bulunmuyor.</div>';
    };

    qs('[data-pa-company]', root)?.addEventListener('change', () => {
        const companyId = companySelect?.value || '';
        if (studioSelect) {
            studioSelect.innerHTML = '<option value="">Tüm Stüdyolar</option>' + studios
                .filter((studio) => !companyId || String(studio.company?.id || studio.company_id || '') === String(companyId))
                .map((studio) => `<option value="${studio.id}">${escapeHtml(studio.name || 'Stüdyo')}</option>`)
                .join('');
        }
        handleAsync(load);
    });

    ['[data-pa-status]', '[data-pa-type]', '[data-pa-date-from]', '[data-pa-date-to]', '[data-pa-studio]', '[data-pa-search]'].forEach((selector) => {
        qs(selector, root)?.addEventListener('input', () => handleAsync(load));
        qs(selector, root)?.addEventListener('change', () => handleAsync(load));
    });
    await load();
};

const renderSettingsPage = async (root) => {
    root.innerHTML = `
        ${pageHeader('Ayarlar', 'Uygulama Ayarları', 'Web panel teması ve bildirim testi.', '<span class="badge-pill">Web</span>')}
        <div class="data-grid">
            <div class="panel-card">
                <div class="section-title" style="margin-bottom:0.75rem">Tema</div>
                <p style="font-size:0.78rem;color:var(--text-muted);line-height:1.55">Web panel mevcut sistem temasını kullanır. Mobil uygulamada varsayılan light, kullanıcı seçerse dark kalır.</p>
            </div>
            <div class="panel-card">
                <div class="section-title" style="margin-bottom:0.75rem">Bildirim Testi</div>
                <button class="button-primary" data-test-notification style="justify-content:center">Test Bildirimi Gönder</button>
            </div>
        </div>
    `;
    qs('[data-test-notification]', root)?.addEventListener('click', () => handleAsync(async () => {
        await apiFetch('/notifications/test', { method: 'POST', body: {} });
        showToast('Test bildirimi tetiklendi.', 'success');
    }));
};

/* ── Hakedişler ─────────────────────────────────────────────── */

const renderEarningsPage = async (root) => {
    const canManage = ['admin', 'yonetici'].includes(adminConfig.role);
    const hasPersonalEarnings = ['supervisor', 'artist', 'designer', 'info', 'sofor', 'calisan'].includes(adminConfig.role);
    const locksToOwnStudio = false;
    let managing = canManage && !hasPersonalEarnings;
    let studios = [];
    let selectedStudioId = '';
    let selectedStaffId = '';
    let selectedStatus = 'pending';
    let dateFrom = '';
    let dateTo = '';

    root.innerHTML = `
        ${pageHeader('Finans', 'Hakedişler', 'Tamamlanan dövme işlemlerinin personel komisyonları ve ödeme durumu.', '<span class="badge-pill badge-pill--success">Komisyon Takibi</span>')}
        <div class="panel-card">
            <div class="earnings-toolbar">
                ${canManage && hasPersonalEarnings ? `
                    <div style="display:flex;gap:0.5rem" data-earnings-modes>
                        <button class="button-primary" data-earnings-mode="mine">Hakedişlerim</button>
                        <button class="button-secondary" data-earnings-mode="staff">Personel</button>
                    </div>
                ` : '<div></div>'}
                ${canManage ? `
                    <div class="field-wrap" data-earnings-studio-wrap style="min-width:min(100%,280px);${managing && !locksToOwnStudio ? '' : 'display:none'}">
                        <label class="field-label">Stüdyo</label>
                        <select class="field-select" data-earnings-studio></select>
                    </div>
                    ${locksToOwnStudio ? '<div class="badge-pill" data-earnings-locked-studio style="display:none">Stüdyo yükleniyor...</div>' : ''}
                ` : ''}
            </div>
            <div data-earnings-content style="margin-top:1rem">${skeletonGrid(5)}</div>
        </div>
    `;

    const content = qs('[data-earnings-content]', root);
    const studioWrap = qs('[data-earnings-studio-wrap]', root);
    const studioSelect = qs('[data-earnings-studio]', root);
    const lockedStudioLabel = qs('[data-earnings-locked-studio]', root);

    const summaryCards = (summary = {}) => `
        <div class="earnings-metrics">
            <article class="metric-card">
                <div class="section-eyebrow" style="color:var(--warning)">Alınacak</div>
                <div class="earnings-metric-value">${formatMoney(summary.pending_total)}</div>
                <div class="earnings-metric-helper">${Number(summary.pending_count || 0)} ödeme bekliyor</div>
            </article>
            <article class="metric-card">
                <div class="section-eyebrow" style="color:var(--success)">Ödenen</div>
                <div class="earnings-metric-value">${formatMoney(summary.paid_total)}</div>
                <div class="earnings-metric-helper">${Number(summary.paid_count || 0)} ödeme tamamlandı</div>
            </article>
            <article class="metric-card">
                <div class="section-eyebrow">Toplam Hakediş</div>
                <div class="earnings-metric-value">${formatMoney(summary.total)}</div>
                <div class="earnings-metric-helper">Tamamlanan dövme işlemleri</div>
            </article>
            <article class="metric-card">
                <div class="section-eyebrow" style="color:var(--info)">Son 7 Gün</div>
                <div class="earnings-metric-value">${formatMoney(summary.last_7_days_pending_total)}</div>
                <div class="earnings-metric-helper">Alınacak ödeme</div>
            </article>
        </div>
    `;

    const earningCard = (earning, showUser = false) => {
        const paid = earning.status === 'paid';
        return `
            <article class="list-card" data-earning-id="${earning.id}" style="padding:0.9rem 1rem">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem">
                    <div style="min-width:0">
                        <div style="font-size:0.86rem;font-weight:650;color:var(--text-main)">
                            ${escapeHtml(showUser ? (earning.user_name || 'Personel') : (earning.studio_name || 'Stüdyo'))}
                        </div>
                        <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">
                            ${escapeHtml(earning.appointment?.customer_name || 'Dövme randevusu')} · ${formatDateTime(earning.appointment?.appointment_at)}
                        </div>
                    </div>
                    <span class="badge-pill ${paid ? 'badge-pill--success' : 'badge-pill--warning'}">${paid ? 'Ödendi' : 'Bekliyor'}</span>
                </div>
                <div class="earnings-detail-grid">
                    ${statBlock('Dövme Fiyatı', formatMoney(earning.gross_amount))}
                    ${statBlock('Komisyon', `%${Number(earning.commission_rate || 0).toLocaleString('tr-TR')}`)}
                    ${statBlock('Hakediş', `<span style="color:var(--success)">${formatMoney(earning.earning_amount)}</span>`)}
                    ${statBlock('Ödeme Bilgisi', paid ? `${formatDateTime(earning.paid_at)}${earning.paid_by ? ` · ${escapeHtml(earning.paid_by)}` : ''}` : 'Ödeme bekliyor')}
                </div>
                ${managing && !paid ? `
                    <div style="display:flex;justify-content:flex-end;margin-top:0.75rem">
                        <button class="button-primary" data-mark-earning-paid style="padding:0.45rem 0.8rem;font-size:0.75rem">Ödendi Olarak İşaretle</button>
                    </div>
                ` : ''}
            </article>
        `;
    };

    const managementFilters = (data = {}) => {
        const staff = data.staff || [];
        return `
            <div class="panel-card" style="margin:1rem 0;padding:1rem">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem">
                    <div class="field-wrap">
                        <label class="field-label">Personel</label>
                        <select class="field-select" data-earnings-staff-filter>
                            <option value="">Tüm personel</option>
                            ${staff.map((person) => `
                                <option value="${person.id}" ${String(person.id) === String(selectedStaffId) ? 'selected' : ''}>
                                    ${escapeHtml(person.name || 'Personel')}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Durum</label>
                        <select class="field-select" data-earnings-status-filter>
                            <option value="pending" ${selectedStatus === 'pending' ? 'selected' : ''}>Ödenmeyen</option>
                            <option value="paid" ${selectedStatus === 'paid' ? 'selected' : ''}>Ödenen</option>
                            <option value="" ${selectedStatus === '' ? 'selected' : ''}>Tümü</option>
                        </select>
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Başlangıç</label>
                        <input class="field-input" type="date" value="${escapeHtml(dateFrom)}" data-earnings-date-from>
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Bitiş</label>
                        <input class="field-input" type="date" value="${escapeHtml(dateTo)}" data-earnings-date-to>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:0.8rem">
                    <button class="button-secondary" data-earnings-clear-filters>Filtreleri Temizle</button>
                </div>
            </div>
        `;
    };

    const personalFilters = () => `
        <div class="panel-card" style="margin:1rem 0;padding:1rem">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem">
                <div class="field-wrap">
                    <label class="field-label">Durum</label>
                    <select class="field-select" data-earnings-status-filter>
                        <option value="pending" ${selectedStatus === 'pending' ? 'selected' : ''}>Ödenmeyen</option>
                        <option value="paid" ${selectedStatus === 'paid' ? 'selected' : ''}>Ödenen</option>
                        <option value="" ${selectedStatus === '' ? 'selected' : ''}>Tümü</option>
                    </select>
                </div>
                <div class="field-wrap">
                    <label class="field-label">Başlangıç</label>
                    <input class="field-input" type="date" value="${escapeHtml(dateFrom)}" data-earnings-date-from>
                </div>
                <div class="field-wrap">
                    <label class="field-label">Bitiş</label>
                    <input class="field-input" type="date" value="${escapeHtml(dateTo)}" data-earnings-date-to>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:0.8rem">
                <button class="button-secondary" data-earnings-clear-filters>Filtreleri Temizle</button>
            </div>
        </div>
    `;

    const staffCard = (person) => {
        const isCurrentUser = String(person.id) === String(adminConfig.userId);
        return `
            <article class="list-card" data-earning-user="${person.id}" style="padding:0.9rem 1rem">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem">
                    <div>
                        <div style="font-size:0.86rem;font-weight:650;color:var(--text-main)">${escapeHtml(person.name || 'Personel')}</div>
                        <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">${escapeHtml(roleLabel(person.role))} · ${Number(person.earning_count || 0)} kayıt</div>
                    </div>
                    <span class="badge-pill ${roleBadgeClass(person.role)}">${escapeHtml(roleLabel(person.role))}</span>
                </div>
                <div class="earnings-detail-grid">
                    ${statBlock('Bekleyen', formatMoney(person.pending_total))}
                    ${statBlock('Ödenen', formatMoney(person.paid_total))}
                    <div class="field-wrap">
                        <label class="field-label">Komisyon Oranı</label>
                        <div style="display:flex;gap:0.4rem">
                            <input class="field-input" data-commission-rate type="number" min="0" max="100" step="0.01" value="${Number(person.commission_rate || 0)}" ${isCurrentUser && adminConfig.isSupervisor ? 'disabled' : ''}>
                            <button class="button-secondary" data-save-commission style="padding:0.45rem 0.7rem;font-size:0.74rem" ${isCurrentUser && adminConfig.isSupervisor ? 'disabled' : ''}>Kaydet</button>
                        </div>
                    </div>
                </div>
            </article>
        `;
    };

    const bindManagementActions = (data) => {
        qs('[data-earnings-staff-filter]', root)?.addEventListener('change', (event) => {
            selectedStaffId = event.target.value;
            handleAsync(load);
        });
        qs('[data-earnings-status-filter]', root)?.addEventListener('change', (event) => {
            selectedStatus = event.target.value;
            handleAsync(load);
        });
        qs('[data-earnings-date-from]', root)?.addEventListener('change', (event) => {
            dateFrom = event.target.value;
            handleAsync(load);
        });
        qs('[data-earnings-date-to]', root)?.addEventListener('change', (event) => {
            dateTo = event.target.value;
            handleAsync(load);
        });
        qs('[data-earnings-clear-filters]', root)?.addEventListener('click', () => {
            selectedStaffId = '';
            selectedStatus = 'pending';
            dateFrom = '';
            dateTo = '';
            handleAsync(load);
        });

        root.querySelectorAll('[data-save-commission]').forEach((button) => {
            button.addEventListener('click', () => handleAsync(async () => {
                const card = button.closest('[data-earning-user]');
                const userId = card?.getAttribute('data-earning-user');
                const commissionRate = Number(qs('[data-commission-rate]', card)?.value);
                if (!Number.isFinite(commissionRate) || commissionRate < 0 || commissionRate > 100) {
                    throw new Error('Komisyon oranı 0 ile 100 arasında olmalıdır.');
                }
                await apiFetch(`/studios/${selectedStudioId}/users/${userId}/commission`, {
                    method: 'PATCH',
                    body: { commission_rate: commissionRate },
                });
                showToast('Komisyon oranı güncellendi.', 'success');
                await load();
            }));
        });

        root.querySelectorAll('[data-mark-earning-paid]').forEach((button) => {
            button.addEventListener('click', () => handleAsync(async () => {
                const card = button.closest('[data-earning-id]');
                const earning = data.earnings.find((item) => String(item.id) === card?.getAttribute('data-earning-id'));
                if (!window.confirm(`${earning?.user_name || 'Personel'} için ${formatMoney(earning?.earning_amount)} ödendi olarak işaretlensin mi?`)) return;
                await apiFetch(`/studios/${selectedStudioId}/earnings/${earning.id}/paid`, {
                    method: 'PATCH',
                    body: {},
                });
                showToast('Hakediş ödendi olarak işaretlendi ve personele bildirim gönderildi.', 'success');
                await load();
            }));
        });
    };

    const bindFilterActions = () => {
        qs('[data-earnings-status-filter]', root)?.addEventListener('change', (event) => {
            selectedStatus = event.target.value;
            handleAsync(load);
        });
        qs('[data-earnings-date-from]', root)?.addEventListener('change', (event) => {
            dateFrom = event.target.value;
            handleAsync(load);
        });
        qs('[data-earnings-date-to]', root)?.addEventListener('change', (event) => {
            dateTo = event.target.value;
            handleAsync(load);
        });
        qs('[data-earnings-clear-filters]', root)?.addEventListener('click', () => {
            selectedStaffId = '';
            selectedStatus = 'pending';
            dateFrom = '';
            dateTo = '';
            handleAsync(load);
        });
    };

    const load = async () => {
        if (managing && !selectedStudioId) {
            content.innerHTML = '<div class="empty-state">Hakedişlerini yönetebileceğiniz stüdyo bulunmuyor.</div>';
            return;
        }

        content.innerHTML = skeletonGrid(5);
        const params = new URLSearchParams();
        if (selectedStaffId) params.set('user_id', selectedStaffId);
        if (selectedStatus) params.set('status', selectedStatus);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        const payload = await apiFetch(managing
            ? `/studios/${selectedStudioId}/earnings${params.toString() ? `?${params.toString()}` : ''}`
            : `/earnings/me${params.toString() ? `?${params.toString()}` : ''}`);
        const data = payload.data || {};
        const earnings = data.earnings || [];

        content.innerHTML = `
            ${summaryCards(data.summary)}
            ${managing ? managementFilters(data) : personalFilters()}
            ${managing ? `
                <div class="earnings-layout">
                    <section>
                        <div class="section-title" style="margin-bottom:0.75rem">Personel Komisyonları</div>
                        <div class="list-stack">
                            ${(data.staff || []).map(staffCard).join('') || '<div class="empty-state">Aktif personel bulunmuyor.</div>'}
                        </div>
                    </section>
                    <section>
                        <div class="section-title" style="margin-bottom:0.75rem">Ödeme Detayları</div>
                        <div class="list-stack">
                            ${earnings.map((earning) => earningCard(earning, true)).join('') || '<div class="empty-state">Hakediş kaydı bulunmuyor.</div>'}
                        </div>
                    </section>
                </div>
            ` : `
                <section style="margin-top:1rem">
                    <div class="section-title" style="margin-bottom:0.75rem">Hakediş Geçmişim</div>
                    <div class="list-stack">
                        ${earnings.map((earning) => earningCard(earning)).join('') || '<div class="empty-state">Tamamlanan dövmelerden oluşmuş hakediş bulunmuyor.</div>'}
                    </div>
                </section>
            `}
        `;

        if (managing) bindManagementActions(data);
        else bindFilterActions();
    };

    if (canManage) {
        const payload = await apiFetch('/studios/options');
        studios = uniqueById(payload.data || []);
        selectedStudioId = studios[0]?.id ? String(studios[0].id) : '';
        if (studioSelect) {
            studioSelect.innerHTML = studios.map((studio) =>
                `<option value="${studio.id}">${escapeHtml(studio.name)}</option>`
            ).join('');
            studioSelect.value = selectedStudioId;
            if (lockedStudioLabel) {
                lockedStudioLabel.textContent = studios[0]?.name
                    ? `Stüdyo: ${studios[0].name}`
                    : 'Atanmış stüdyo bulunamadı';
            }
            studioSelect.addEventListener('change', () => {
                selectedStudioId = studioSelect.value;
                selectedStaffId = '';
                handleAsync(load);
            });
        }
    }

    root.querySelectorAll('[data-earnings-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            managing = button.getAttribute('data-earnings-mode') === 'staff';
            root.querySelectorAll('[data-earnings-mode]').forEach((item) => {
                const active = (item.getAttribute('data-earnings-mode') === 'staff') === managing;
                item.classList.toggle('button-primary', active);
                item.classList.toggle('button-secondary', !active);
            });
            if (studioWrap) studioWrap.style.display = managing && !locksToOwnStudio ? '' : 'none';
            if (lockedStudioLabel) lockedStudioLabel.style.display = managing ? '' : 'none';
            handleAsync(load);
        });
    });

    await load();
};

/* ── Sayfa yönlendirici ─────────────────────────────────────── */

const pageInitializers = [
    ['[data-admin-dashboard]',    renderDashboard],
    ['[data-admin-companies]',    renderCompaniesPage],
    ['[data-admin-users]',        renderUsersPage],
    ['[data-admin-appointments]', renderAppointmentsPage],
    ['[data-admin-appointment-requests]', renderAppointmentRequestsPage],
    ['[data-admin-my-notifications]', renderMyNotificationsPage],
    ['[data-admin-discovery]', renderDiscoveryPage],
    ['[data-admin-public-studio-detail]', renderPublicStudioDetailPage],
    ['[data-admin-public-artist-detail]', renderPublicArtistDetailPage],
    ['[data-admin-profile]', renderProfilePage],
    ['[data-admin-profile-appointments]', renderProfileAppointmentsPage],
    ['[data-admin-earnings]', renderEarningsPage],
    ['[data-admin-settings]', renderSettingsPage],
    ['[data-admin-studios]',      renderStudiosPage],
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
