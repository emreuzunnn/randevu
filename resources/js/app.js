import './bootstrap';

/* ── Yardımcı fonksiyonlar ─────────────────────────────────── */

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
    isAdmin:            meta('admin-is-admin') === '1',
};

/* ── Durum & rol çevirileri ────────────────────────────────── */

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
    admin:          'Admin',
    yonetici:       'Yönetici',
    studio_admin:   'Stüdyo Yöneticisi',
    supervisor:     'Süpervizör',
    designer:       'Tasarımcı',
    artist:         'Artist',
    info:           'Info',
    sofor:          'Şoför',
    calisan:        'Çalışan',
    kullanici_rol:  'Kullanıcı (Rol)',
    kullanici:      'Kullanıcı',
};

const statusLabel = (status) => STATUS_LABELS[status] ?? status;
const roleLabel   = (role)   => ROLE_LABELS[role]   ?? role;

/* ── Toast ─────────────────────────────────────────────────── */

const toastRoot = () => qs('#admin-toast-root');

const showToast = (message, type = 'info') => {
    const root = toastRoot();
    if (!root) return;

    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.innerHTML = `
        <div class="text-sm font-semibold">${type === 'error' ? 'İşlem Başarısız' : 'Bilgi'}</div>
        <div class="mt-1 text-sm text-slate-300">${escapeHtml(message)}</div>
    `;

    root.appendChild(toast);

    window.setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-6px)';
        toast.style.transition = 'opacity 200ms ease, transform 200ms ease';
        window.setTimeout(() => toast.remove(), 220);
    }, 3400);
};

/* ── API yardımcısı ────────────────────────────────────────── */

const apiFetch = async (path, options = {}) => {
    const url = `${adminConfig.apiBase}${path}`;
    const headers = new Headers(options.headers || {});

    headers.set('Accept', 'application/json');

    if (adminConfig.token) {
        headers.set('Authorization', `Bearer ${adminConfig.token}`);
    }

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

/* ── Tarih formatı ─────────────────────────────────────────── */

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

/* ── Durum CSS sınıfı ──────────────────────────────────────── */

const statusClass = (status) => {
    const map = {
        completed:   'badge-pill badge-pill--success',
        confirmed:   'badge-pill badge-pill--info',
        pending:     'badge-pill badge-pill--warning',
        cancelled:   'badge-pill badge-pill--danger',
        rescheduled: 'badge-pill badge-pill--warning',
        working:     'badge-pill badge-pill--success',
        break:       'badge-pill badge-pill--warning',
        transfer:    'badge-pill badge-pill--info',
        active:      'badge-pill badge-pill--success',
    };
    return map[status] || 'badge-pill';
};

/* ── Yardımcı render ───────────────────────────────────────── */

const skeletonGrid = (count = 4) =>
    Array.from({ length: count }, () => '<div class="skeleton"></div>').join('');

const animateCounters = (scope = document) => {
    scope.querySelectorAll('[data-counter]').forEach((node) => {
        const target   = Number(node.getAttribute('data-counter') || '0');
        const duration = 700;
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

/* ── Dashboard ─────────────────────────────────────────────── */

const renderDashboard = async (root) => {
    root.innerHTML = `
        <section class="hero-card">
            <div class="section-eyebrow">Merkez Panorama</div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-6 pb-2">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-bold tracking-tight">Operasyonun nabzını tek bakışta yakala.</h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-muted">
                        Canlı metrikler, dönemsel raporlar ve sahadaki hareketler tek akışta birleşir.
                        Doğru anda doğru kararı vermek için tüm tablo tek yerde toplanır.
                    </p>
                </div>
                <div class="badge-pill badge-pill--info">Anlık Operasyon Takibi</div>
            </div>
        </section>
        ${adminConfig.isAdmin ? `<section class="panel-card" data-dashboard-companies>${skeletonGrid(3)}</section>` : ''}
        <section class="metric-grid" data-dashboard-metrics>${skeletonGrid(4)}</section>
        <section class="data-grid" data-dashboard-reports>${skeletonGrid(3)}</section>
        <section class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="panel-card" data-dashboard-studios>${skeletonGrid(1)}</div>
            <div class="panel-card" data-dashboard-appointments>${skeletonGrid(1)}</div>
        </section>
    `;

    const payload = await apiFetch('/home');
    const data    = payload.data;

    qs('[data-dashboard-metrics]', root).innerHTML = [
        ['Toplam Randevu',  data.summary.total_appointments,    'Tüm dönem'],
        ['İptal',           data.summary.cancelled_appointments, 'Risk takibi'],
        ['Aktif Ekip',      data.summary.active_staff_count,    'Canlı personel'],
        ['Transfer',        data.summary.transfer_count,        'Sürücü görevleri'],
    ].map(([label, value, helper], index) => `
        <article class="metric-card animate-stagger-${(index % 3) + 1}">
            <div class="text-xs font-semibold" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.10em">${label}</div>
            <div class="mt-3 text-4xl font-bold" data-counter="${value}">0</div>
            <div class="mt-2 text-xs" style="color:var(--text-muted)">${helper}</div>
        </article>
    `).join('');

    qs('[data-dashboard-reports]', root).innerHTML = Object.values(data.reports || {}).map((report, index) => `
        <article class="data-card animate-stagger-${(index % 3) + 1}">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="section-title">${escapeHtml(report.label)} Rapor</div>
                    <div class="mt-1 text-xs" style="color:var(--text-muted)">${escapeHtml(report.date_from)} — ${escapeHtml(report.date_to)}</div>
                </div>
                <span class="badge-pill badge-pill--info">Dönem</span>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-2.5 text-sm">
                <div class="list-card">
                    <div class="text-xs" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.08em">Toplam</div>
                    <div class="mt-2 text-2xl font-bold" data-counter="${report.total_appointments}">0</div>
                </div>
                <div class="list-card">
                    <div class="text-xs" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.08em">Tamamlandı</div>
                    <div class="mt-2 text-2xl font-bold" data-counter="${report.completed_appointments}">0</div>
                </div>
                <div class="list-card">
                    <div class="text-xs" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.08em">İptal</div>
                    <div class="mt-2 text-2xl font-bold" data-counter="${report.cancelled_appointments}">0</div>
                </div>
                <div class="list-card">
                    <div class="text-xs" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.08em">Bekliyor</div>
                    <div class="mt-2 text-2xl font-bold" data-counter="${report.pending_appointments}">0</div>
                </div>
            </div>
        </article>
    `).join('');

    qs('[data-dashboard-studios]', root).innerHTML = `
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="section-eyebrow">Stüdyo Özeti</div>
                <h2 class="mt-2 section-title">Stüdyo Performansı</h2>
            </div>
            <span class="badge-pill">${data.studios.length} lokasyon</span>
        </div>
        <div class="mt-5 table-shell">
            <table>
                <thead>
                    <tr>
                        <th>Stüdyo</th>
                        <th>Konum</th>
                        <th>Aktif Ekip</th>
                        <th>Randevu</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.studios.map((studio) => `
                        <tr>
                            <td class="font-semibold">${escapeHtml(studio.name)}</td>
                            <td style="color:var(--text-muted)">${escapeHtml(studio.location || '—')}</td>
                            <td>${studio.active_staff_count}</td>
                            <td>${studio.appointments_count}</td>
                        </tr>
                    `).join('') || '<tr><td colspan="4" style="color:var(--text-muted)">Stüdyo bulunamadı.</td></tr>'}
                </tbody>
            </table>
        </div>
    `;

    qs('[data-dashboard-appointments]', root).innerHTML = `
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="section-eyebrow">Günlük Akış</div>
                <h2 class="mt-2 section-title">Bugünün Randevuları</h2>
            </div>
            <span class="badge-pill badge-pill--warning">${data.today_appointments.length} kayıt</span>
        </div>
        <div class="mt-5 list-stack">
            ${data.today_appointments.map((appointment) => `
                <div class="list-card">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold">${escapeHtml(`${appointment.customer.first_name} ${appointment.customer.last_name}`)}</div>
                            <div class="mt-1 text-xs" style="color:var(--text-muted)">${escapeHtml(appointment.customer.hotel_name || appointment.studio || '—')}</div>
                            <div class="mt-1.5 text-xs" style="color:var(--text-subtle)">${formatDateTime(appointment.appointment_at)}</div>
                        </div>
                        <span class="${statusClass(appointment.status)}">${statusLabel(appointment.status)}</span>
                    </div>
                </div>
            `).join('') || '<div class="empty-state" style="padding:1.5rem">Bugün için randevu bulunmuyor.</div>'}
        </div>
    `;

    if (adminConfig.isAdmin) {
        const compPayload = await apiFetch('/companies').catch(() => ({ data: [] }));
        const companies = compPayload.data || [];
        qs('[data-dashboard-companies]', root).innerHTML = `
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="section-eyebrow">Şirket Panorama</div>
                    <h2 class="mt-2 section-title">Şirket Randevu Hacimleri</h2>
                </div>
                <span class="badge-pill">${companies.length} şirket</span>
            </div>
            <div class="mt-5 table-shell">
                <table>
                    <thead>
                        <tr>
                            <th>Şirket</th>
                            <th>Dükkan</th>
                            <th>Stüdyo</th>
                            <th>Randevu</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${companies.map((company) => `
                            <tr>
                                <td class="font-semibold">${escapeHtml(company.name)}</td>
                                <td>${company.shop_count} / ${company.max_shop_count === 0 ? '∞' : company.max_shop_count}</td>
                                <td>${company.studio_count} / ${company.max_studio_count === 0 ? '∞' : company.max_studio_count}</td>
                                <td><strong data-counter="${company.appointment_count}">0</strong></td>
                            </tr>
                        `).join('') || '<tr><td colspan="4" style="color:var(--text-muted)">Şirket bulunamadı.</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    }

    animateCounters(root);
};

/* ── Kullanıcılar ──────────────────────────────────────────── */

const renderUsersPage = async (root) => {
    const roles = adminConfig.isAdmin
        ? ['admin', 'yonetici', 'supervisor', 'sofor', 'calisan']
        : ['supervisor', 'sofor', 'calisan'];

    root.innerHTML = `
        <section class="hero-card">
            <div class="section-eyebrow">Ekip Yönetimi</div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-6">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-bold tracking-tight">Doğru ekibi doğru stüdyoya hızla yerleştir.</h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-muted">
                        Personel listesi, roller ve durum bilgileri tek panelde görünür. Ekip düzenini bozmadan hızlı güncelleme yapabilirsiniz.
                    </p>
                </div>
                <div class="badge-pill badge-pill--success">Ekip Yönetimi</div>
            </div>
        </section>
        <section class="grid gap-5 xl:grid-cols-[1.08fr_0.92fr]">
            <div class="panel-card">
                <div class="action-row">
                    <div class="field-wrap min-w-[240px] flex-1">
                        <label class="field-label">Stüdyo Seç</label>
                        <select class="field-select" data-users-studio-select></select>
                    </div>
                    <button class="button-secondary" data-users-refresh>Yenile</button>
                </div>
                <div class="mt-5 list-stack" data-users-list>${skeletonGrid(4)}</div>
            </div>
            <div class="form-shell">
                <div class="section-eyebrow">Yeni Personel</div>
                <h2 class="mt-2 section-title">Kullanıcı Ekle</h2>
                <form class="mt-5 form-grid" data-users-create-form>
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
                        <div class="field-wrap">
                            <label class="field-label">Stüdyo</label>
                            <select class="field-select" name="studio_id" data-users-create-studio></select>
                        </div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Şifre</label><input class="field-input" name="password" type="password" required></div>
                        <div class="field-wrap"><label class="field-label">Şifre Tekrar</label><input class="field-input" name="password_confirmation" type="password" required></div>
                    </div>
                    <button class="button-primary mt-1" type="submit">Kullanıcı Oluştur</button>
                </form>
            </div>
        </section>
    `;

    const studioSelect       = qs('[data-users-studio-select]', root);
    const createStudioSelect = qs('[data-users-create-studio]', root);
    const listNode           = qs('[data-users-list]', root);
    const form               = qs('[data-users-create-form]', root);
    const roleSelect         = qs('[data-users-role-select]', root);

    roleSelect.innerHTML = roles.map((role) =>
        `<option value="${role}">${roleLabel(role)}</option>`
    ).join('');

    const loadStudios = async () => {
        const payload = await apiFetch('/studios/options');
        const studios = payload.data || [];
        const options = studios.map((studio) =>
            `<option value="${studio.id}">${escapeHtml(studio.name)}</option>`
        ).join('');
        studioSelect.innerHTML   = options;
        createStudioSelect.innerHTML = options;
        return studios;
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
            ? users.map((user, index) => `
                <article class="data-card animate-stagger-${(index % 3) + 1}" data-user-card data-user-id="${user.id}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-base font-semibold">${escapeHtml(user.name)}</div>
                            <div class="mt-1 text-xs" style="color:var(--text-muted)">${escapeHtml(user.email)}</div>
                        </div>
                        <span class="${statusClass(user.status)}">${statusLabel(user.status)}</span>
                    </div>
                    <div class="mt-4 form-grid form-grid--split">
                        <div class="field-wrap">
                            <label class="field-label">Rol</label>
                            <select class="field-select" data-user-role>
                                ${roles.map((role) =>
                                    `<option value="${role}" ${user.role === role ? 'selected' : ''}>${roleLabel(role)}</option>`
                                ).join('')}
                            </select>
                        </div>
                        <div class="field-wrap">
                            <label class="field-label">Durum</label>
                            <select class="field-select" data-user-status>
                                ${['working', 'break', 'transfer'].map((s) =>
                                    `<option value="${s}" ${user.status === s ? 'selected' : ''}>${statusLabel(s)}</option>`
                                ).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 action-row">
                        <label class="inline-flex items-center gap-2 text-xs" style="color:var(--text-muted)">
                            <input type="checkbox" data-user-active ${user.is_active ? 'checked' : ''}>
                            Aktif
                        </label>
                        <button class="button-secondary" data-user-save>Güncelle</button>
                    </div>
                </article>
            `).join('')
            : '<div class="empty-state">Bu stüdyoda kullanıcı bulunmuyor.</div>';

        listNode.querySelectorAll('[data-user-save]').forEach((button) => {
            button.addEventListener('click', () => handleAsync(async () => {
                const card   = button.closest('[data-user-card]');
                const userId = card?.getAttribute('data-user-id');

                await apiFetch(`/studios/${studioSelect.value}/users/${userId}`, {
                    method: 'PATCH',
                    body: {
                        role:      qs('[data-user-role]', card)?.value,
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
    await renderUsers();

    studioSelect.addEventListener('change', () => handleAsync(renderUsers));
    qs('[data-users-refresh]', root)?.addEventListener('click', () => handleAsync(renderUsers));

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        handleAsync(async () => {
            const formData = new FormData(form);
            await apiFetch('/users', {
                method: 'POST',
                body: Object.fromEntries(formData.entries()),
            });
            form.reset();
            createStudioSelect.value = studioSelect.value;
            showToast('Yeni kullanıcı eklendi.', 'success');
            await renderUsers();
        });
    });
};

/* ── Randevular ────────────────────────────────────────────── */

const renderAppointmentsPage = async (root) => {
    root.innerHTML = `
        <section class="hero-card">
            <div class="section-eyebrow">Randevu Akışı</div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-6">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-bold tracking-tight">Her randevuyu düzenli, hızlı ve kontrollü ilerlet.</h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-muted">
                        Liste, oluşturma ve durum güncelleme akışlarını tek merkezde toplar. Ekibin bir sonraki adımı her an net görünür.
                    </p>
                </div>
                <div class="badge-pill badge-pill--warning">Canlı Operasyon Akışı</div>
            </div>
        </section>
        <section class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="panel-card">
                <div class="action-row">
                    <div class="field-wrap min-w-[240px] flex-1">
                        <label class="field-label">Stüdyo Seç</label>
                        <select class="field-select" data-appointments-studio-select></select>
                    </div>
                    <button class="button-secondary" data-appointments-refresh>Yenile</button>
                </div>
                <div class="mt-5 list-stack" data-appointments-list>${skeletonGrid(4)}</div>
            </div>
            <div class="form-shell">
                <div class="section-eyebrow">Yeni Randevu</div>
                <h2 class="mt-2 section-title">Randevu Oluştur</h2>
                <form class="mt-5 form-grid" data-appointment-form>
                    <div class="field-wrap"><label class="field-label">Stüdyo</label><select class="field-select" name="studio_id" data-appointment-studio></select></div>
                    <div class="field-wrap"><label class="field-label">Fiş / Görsel Yolu</label><input class="field-input" name="source_image_path"></div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Ad</label><input class="field-input" name="first_name" required></div>
                        <div class="field-wrap"><label class="field-label">Soyad</label><input class="field-input" name="last_name" required></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="phone_number"></div>
                        <div class="field-wrap"><label class="field-label">Ülke Kodu</label><input class="field-input" name="phone_country_code" value="+90"></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Otel</label><input class="field-input" name="hotel_name"></div>
                        <div class="field-wrap"><label class="field-label">Oda No</label><input class="field-input" name="room_number"></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Kişi Sayısı</label><input class="field-input" type="number" min="1" name="pax" required></div>
                        <div class="field-wrap"><label class="field-label">Randevu Tipi</label><input class="field-input" name="appointment_type" value="standard"></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Tarih</label><input class="field-input" type="date" name="date" required></div>
                        <div class="field-wrap"><label class="field-label">Saat</label><input class="field-input" type="time" name="time" required></div>
                    </div>
                    <div class="field-wrap"><label class="field-label">Yer</label><input class="field-input" name="place"></div>
                    <div class="field-wrap">
                        <label class="field-label">Sürücü</label>
                        <select class="field-select" name="assigned_driver_user_id" data-driver-select>
                            <option value="">Sürücü seçin</option>
                        </select>
                    </div>
                    <div class="field-wrap"><label class="field-label">Notlar</label><textarea class="field-textarea" rows="3" name="notes"></textarea></div>
                    <button class="button-primary mt-1" type="submit">Randevuyu Kaydet</button>
                </form>
            </div>
        </section>
    `;

    const studioSelect       = qs('[data-appointments-studio-select]', root);
    const createStudioSelect = qs('[data-appointment-studio]', root);
    const listNode           = qs('[data-appointments-list]', root);
    const form               = qs('[data-appointment-form]', root);
    const driverSelect       = qs('[data-driver-select]', root);

    const loadStudios = async () => {
        const payload = await apiFetch('/studios/options');
        const studios = payload.data || [];
        const options = studios.map((studio) =>
            `<option value="${studio.id}">${escapeHtml(studio.name)}</option>`
        ).join('');
        studioSelect.innerHTML       = options;
        createStudioSelect.innerHTML = options;
    };

    let supportArtists = [];

    const loadSupport = async (studioId) => {
        if (!studioId) return;
        const payload = await apiFetch(`/studios/${studioId}/appointment-support`);
        const drivers = payload.data?.drivers || [];
        supportArtists = payload.data?.artists || [];
        driverSelect.innerHTML = `<option value="">Sürücü seçin</option>${drivers.map((driver) => `
            <option value="${driver.id}">${escapeHtml(driver.name)}${driver.phone ? ` — ${escapeHtml(driver.phone)}` : ''}</option>
        `).join('')}`;
    };

    const renderAppointments = async () => {
        if (!studioSelect.value) {
            listNode.innerHTML = '<div class="empty-state">Randevuları görüntülemek için bir stüdyo seçin.</div>';
            return;
        }

        listNode.innerHTML = skeletonGrid(4);
        const payload      = await apiFetch(`/studios/${studioSelect.value}/appointments`);
        const appointments = payload.data || [];

        listNode.innerHTML = appointments.length
            ? appointments.map((appointment, index) => `
                <article class="data-card animate-stagger-${(index % 3) + 1}" data-appointment-id="${appointment.id}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-base font-semibold">${escapeHtml(`${appointment.customer.first_name} ${appointment.customer.last_name}`)}</div>
                            <div class="mt-1 text-xs" style="color:var(--text-muted)">${escapeHtml(appointment.customer.hotel_name || appointment.studio || '—')}</div>
                            <div class="mt-1.5 text-xs" style="color:var(--text-subtle)">${formatDateTime(appointment.appointment_at)}</div>
                        </div>
                        <span class="${statusClass(appointment.status)}">${statusLabel(appointment.status)}</span>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div class="field-wrap">
                            <label class="field-label">Durum</label>
                            <select class="field-select" data-appointment-status>
                                ${['pending', 'confirmed', 'completed', 'cancelled', 'rescheduled'].map((s) => `
                                    <option value="${s}" ${appointment.status === s ? 'selected' : ''}>${statusLabel(s)}</option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="field-wrap">
                            <label class="field-label">Sürücü</label>
                            <select class="field-select" data-appointment-driver>
                                <option value="">Sürücü seçin</option>
                                ${Array.from(driverSelect.options).map((option) => `
                                    <option value="${option.value}" ${String(appointment.assigned_driver_user_id || '') === option.value ? 'selected' : ''}>${escapeHtml(option.textContent || '')}</option>
                                `).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 action-row">
                        <a href="/admin/appointments/${appointment.id}" class="button-ghost">Detay</a>
                        <button class="button-secondary" data-appointment-save>Kaydet</button>
                    </div>
                </article>
            `).join('')
            : '<div class="empty-state">Bu stüdyoda randevu bulunmuyor.</div>';

        listNode.querySelectorAll('[data-appointment-save]').forEach((button) => {
            button.addEventListener('click', () => handleAsync(async () => {
                const card          = button.closest('[data-appointment-id]');
                const appointmentId = card?.getAttribute('data-appointment-id');
                await apiFetch(`/studios/${studioSelect.value}/appointments/${appointmentId}`, {
                    method: 'PATCH',
                    body: {
                        status:                    qs('[data-appointment-status]', card)?.value,
                        assigned_driver_user_id:   qs('[data-appointment-driver]', card)?.value || null,
                    },
                });
                showToast('Randevu güncellendi.', 'success');
                await renderAppointments();
            }));
        });
    };

    await loadStudios();
    await loadSupport(studioSelect.value || createStudioSelect.value);
    await renderAppointments();

    studioSelect.addEventListener('change', () => handleAsync(async () => {
        createStudioSelect.value = studioSelect.value;
        await loadSupport(studioSelect.value);
        await renderAppointments();
    }));

    createStudioSelect.addEventListener('change', () => handleAsync(async () => {
        studioSelect.value = createStudioSelect.value;
        await loadSupport(createStudioSelect.value);
        await renderAppointments();
    }));

    qs('[data-appointments-refresh]', root)?.addEventListener('click', () => handleAsync(renderAppointments));

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        handleAsync(async () => {
            const formData = new FormData(form);
            const body = {
                customer: {
                    first_name:         formData.get('first_name'),
                    last_name:          formData.get('last_name'),
                    phone_country_code: formData.get('phone_country_code') || null,
                    phone_number:       formData.get('phone_number') || null,
                    hotel_name:         formData.get('hotel_name') || null,
                    room_number:        formData.get('room_number') || null,
                },
                pax:                       Number(formData.get('pax')),
                appointment_at:            `${formData.get('date')} ${formData.get('time')}:00`,
                appointment_type:          formData.get('appointment_type') || 'standard',
                notes:                     formData.get('notes') || null,
                source_image_path:         formData.get('source_image_path') || null,
                assigned_driver_user_id:   formData.get('assigned_driver_user_id') || null,
            };

            await apiFetch(`/studios/${formData.get('studio_id')}/appointments`, {
                method: 'POST',
                body,
            });

            showToast('Randevu oluşturuldu.', 'success');
            form.reset();
            await loadSupport(createStudioSelect.value);
            await renderAppointments();
        });
    });
};

/* ── Stüdyolar ─────────────────────────────────────────────── */

const renderStudiosPage = async (root) => {
    root.innerHTML = `
        <section class="hero-card">
            <div class="section-eyebrow">Stüdyo Yönetimi</div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-6">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-bold tracking-tight">Her stüdyoyu net hedeflerle ve güçlü bir görünümle yönet.</h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-muted">
                        Lokasyon, ekip yoğunluğu ve ayar bilgileri tek kartta toplanır. Her stüdyonun durumu ilk bakışta anlaşılır.
                    </p>
                </div>
                <div class="badge-pill">Lokasyon Kontrolü</div>
            </div>
        </section>
        <section class="data-grid" data-studios-grid>${skeletonGrid(3)}</section>
    `;

    const grid    = qs('[data-studios-grid]', root);
    const payload = await apiFetch('/studios/overview');
    const studios = payload.data || [];

    grid.innerHTML = studios.length
        ? studios.map((studio, index) => `
            <article class="data-card animate-stagger-${(index % 3) + 1}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="section-eyebrow">${escapeHtml(studio.shop?.name || 'Dükkan bilgisi yok')}</div>
                        <h2 class="mt-2 text-xl font-bold">${escapeHtml(studio.name)}</h2>
                    </div>
                    <span class="badge-pill badge-pill--info">${studio.appointments_count} randevu</span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2.5 text-sm">
                    <div class="list-card">
                        <div class="text-xs" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.08em">Konum</div>
                        <div class="mt-2 font-semibold text-sm">${escapeHtml(studio.location || '—')}</div>
                    </div>
                    <div class="list-card">
                        <div class="text-xs" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.08em">Aktif Ekip</div>
                        <div class="mt-2 font-semibold text-sm">${studio.active_staff_count}</div>
                    </div>
                </div>
                <form class="mt-5 form-grid" data-studio-form data-studio-id="${studio.id}">
                    <div class="field-wrap"><label class="field-label">Stüdyo Adı</label><input class="field-input" name="name" value="${escapeHtml(studio.name)}"></div>
                    <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location" value="${escapeHtml(studio.location || '')}"></div>
                    <div class="field-wrap"><label class="field-label">Logo Yolu</label><input class="field-input" name="logo_path" value="${escapeHtml(studio.logo_path || '')}"></div>
                    <div class="field-wrap"><label class="field-label">Bildirim Süresi (dk)</label><input class="field-input" type="number" min="0" name="notification_lead_minutes" value="${studio.notification_lead_minutes}"></div>
                    <button class="button-primary mt-1" type="submit">Stüdyo Ayarlarını Kaydet</button>
                </form>
            </article>
        `).join('')
        : '<div class="empty-state">Erişilebilir stüdyo bulunmuyor.</div>';

    grid.querySelectorAll('[data-studio-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            handleAsync(async () => {
                const formData = new FormData(form);
                await apiFetch(`/studios/${form.getAttribute('data-studio-id')}`, {
                    method: 'PATCH',
                    body: Object.fromEntries(formData.entries()),
                });
                showToast('Stüdyo kaydı güncellendi.', 'success');
                await renderStudiosPage(root);
            });
        });
    });
};

/* ── Dükkanlar ─────────────────────────────────────────────── */

const renderShopsPage = async (root) => {
    root.innerHTML = `
        <section class="hero-card">
            <div class="section-eyebrow">Dükkan Yönetimi</div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-6">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-bold tracking-tight">Dükkanlarını tek markanın güçlü şubeleri gibi konumlandır.</h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-muted">
                        Dükkan kartları, yönetici eşleştirmesi ve büyüme planı aynı yerde buluşur. Yapını büyütürken kontrolü elinde tutarsın.
                    </p>
                </div>
                <div class="badge-pill badge-pill--success">Dükkan Yönetimi</div>
            </div>
        </section>
        <section class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="panel-card" data-shops-list>${skeletonGrid(3)}</div>
            <div class="form-shell" data-shops-create></div>
        </section>
    `;

    const listNode   = qs('[data-shops-list]', root);
    const createNode = qs('[data-shops-create]', root);

    const [shopsPayload, managersPayload, companiesPayload] = await Promise.all([
        apiFetch('/shops'),
        adminConfig.isAdmin ? apiFetch('/users/options?roles=yonetici,supervisor') : Promise.resolve({ data: [] }),
        adminConfig.isAdmin ? apiFetch('/companies') : Promise.resolve({ data: [] }),
    ]);

    const shops     = shopsPayload.data || [];
    const managers  = managersPayload.data || [];
    const companies = companiesPayload.data || [];
    const companyMap = Object.fromEntries(companies.map((c) => [String(c.id), c.name]));

    const buildManagerOptions = (selectedId = null) =>
        `<option value="">Yönetici seçin</option>${managers.map((manager) => `
            <option value="${manager.id}" ${String(manager.id) === String(selectedId) ? 'selected' : ''}>
                ${escapeHtml(manager.name)} — ${roleLabel(manager.role)}
            </option>
        `).join('')}`;

    const buildCompanyOptions = () =>
        `<option value="">Şirket seçin</option>${companies.map((company) =>
            `<option value="${company.id}">${escapeHtml(company.name)}</option>`
        ).join('')}`;

    listNode.innerHTML = `
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="section-eyebrow">Dükkan Ağı</div>
                <h2 class="mt-2 section-title">Aktif Dükkanlar</h2>
            </div>
            <span class="badge-pill">${shops.length} dükkan</span>
        </div>
        <div class="mt-5 list-stack">
            ${shops.map((shop, index) => `
                <article class="data-card animate-stagger-${(index % 3) + 1}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-base font-semibold">${escapeHtml(shop.name)}</div>
                            <div class="mt-1 text-xs" style="color:var(--text-muted)">${escapeHtml(shop.location || '—')}${companyMap[String(shop.company_id)] ? ` · ${escapeHtml(companyMap[String(shop.company_id)])}` : ''}</div>
                        </div>
                        <span class="${shop.is_active ? 'badge-pill badge-pill--success' : 'badge-pill badge-pill--danger'}">
                            ${shop.is_active ? 'Aktif' : 'Pasif'}
                        </span>
                    </div>
                    <div class="mt-4 grid gap-2.5 text-sm md:grid-cols-2">
                        <div class="list-card">
                            <div class="text-xs" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.08em">Yönetici</div>
                            <div class="mt-2 font-semibold text-sm">${escapeHtml(shop.manager?.name || '—')}</div>
                        </div>
                        <div class="list-card">
                            <div class="text-xs" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.08em">Bağlı Stüdyolar</div>
                            <div class="mt-2 font-semibold text-sm">${shop.studios.map((s) => escapeHtml(s.name)).join(', ') || '—'}</div>
                        </div>
                    </div>
                    <form class="mt-5 form-grid" data-shop-form data-shop-id="${shop.id}">
                        <div class="field-wrap"><label class="field-label">Dükkan Adı</label><input class="field-input" name="name" value="${escapeHtml(shop.name)}"></div>
                        <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location" value="${escapeHtml(shop.location || '')}"></div>
                        ${adminConfig.isAdmin ? `
                            <div class="field-wrap">
                                <label class="field-label">Yönetici</label>
                                <select class="field-select" name="manager_user_id">
                                    ${buildManagerOptions(shop.manager?.id ?? null)}
                                </select>
                            </div>
                        ` : ''}
                        <button class="button-primary mt-1" type="submit">Kaydet</button>
                    </form>
                </article>
            `).join('') || '<div class="empty-state">Dükkan bulunamadı.</div>'}
        </div>
    `;

    createNode.innerHTML = adminConfig.isAdmin
        ? `
            <div class="section-eyebrow">Yeni Lokasyon</div>
            <h2 class="mt-2 section-title">Yeni Dükkan Oluştur</h2>
            <form class="mt-5 form-grid" data-shop-create-form>
                <div class="field-wrap">
                    <label class="field-label">Şirket</label>
                    <select class="field-select" name="company_id" required>${buildCompanyOptions()}</select>
                </div>
                <div class="field-wrap"><label class="field-label">Dükkan Adı</label><input class="field-input" name="name" required></div>
                <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location"></div>
                <div class="field-wrap">
                    <label class="field-label">Yönetici</label>
                    <select class="field-select" name="manager_user_id">${buildManagerOptions()}</select>
                </div>
                <button class="button-primary mt-1" type="submit">Dükkan Oluştur</button>
            </form>
        `
        : `
            <div class="empty-state">
                <div class="section-title">Dükkan bilgileri senkronize.</div>
                <div class="mt-3 text-sm" style="color:var(--text-muted)">Bu alanda yalnızca size ait dükkan kartları listelenir.</div>
            </div>
        `;

    listNode.querySelectorAll('[data-shop-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            handleAsync(async () => {
                const body = Object.fromEntries(new FormData(form).entries());
                await apiFetch(`/shops/${form.getAttribute('data-shop-id')}`, { method: 'PATCH', body });
                showToast('Dükkan güncellendi.', 'success');
                await renderShopsPage(root);
            });
        });
    });

    const createForm = qs('[data-shop-create-form]', root);
    if (createForm) {
        createForm.addEventListener('submit', (event) => {
            event.preventDefault();
            handleAsync(async () => {
                const body = Object.fromEntries(new FormData(createForm).entries());
                await apiFetch('/shops', { method: 'POST', body });
                showToast('Yeni dükkan eklendi.', 'success');
                await renderShopsPage(root);
            });
        });
    }
};

/* ── Şirketler ─────────────────────────────────────────────── */

const renderCompaniesPage = async (root) => {
    root.innerHTML = `
        <section class="hero-card">
            <div class="section-eyebrow">Şirket Yönetimi</div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-6">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-bold tracking-tight">Tüm şirketleri tek merkezden yönet ve takip et.</h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-muted">
                        Şirket bazlı dükkan, stüdyo ve randevu verilerini anlık görün. Büyüme sınırlarını belirle, operasyonun tamamını denetle.
                    </p>
                </div>
                <div class="badge-pill badge-pill--info">Platform Yönetimi</div>
            </div>
        </section>
        <section class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="panel-card" data-companies-list>${skeletonGrid(3)}</div>
            <div class="form-shell">
                <div class="section-eyebrow">Yeni Şirket</div>
                <h2 class="mt-2 section-title">Şirket Oluştur</h2>
                <form class="mt-5 form-grid" data-company-create-form>
                    <div class="field-wrap"><label class="field-label">Şirket Adı</label><input class="field-input" name="name" required></div>
                    <div class="field-wrap"><label class="field-label">Adres</label><input class="field-input" name="address"></div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="phone"></div>
                        <div class="field-wrap"><label class="field-label">E-posta</label><input class="field-input" name="email" type="email"></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Max Dükkan <span style="color:var(--text-muted)">(0 = sınırsız)</span></label><input class="field-input" type="number" min="0" name="max_shop_count" value="0"></div>
                        <div class="field-wrap"><label class="field-label">Max Stüdyo <span style="color:var(--text-muted)">(0 = sınırsız)</span></label><input class="field-input" type="number" min="0" name="max_studio_count" value="0"></div>
                    </div>
                    <button class="button-primary mt-1" type="submit">Şirket Oluştur</button>
                </form>
            </div>
        </section>
    `;

    const listNode = qs('[data-companies-list]', root);

    const limitBadge = (current, max) => {
        const text = max === 0 ? `${current} / ∞` : `${current} / ${max}`;
        const cls  = max > 0 && current >= max ? 'danger' : 'success';
        return `<span class="badge-pill badge-pill--${cls}">${text}</span>`;
    };

    const renderCompanies = async () => {
        listNode.innerHTML = skeletonGrid(3);
        const payload   = await apiFetch('/companies');
        const companies = payload.data || [];

        listNode.innerHTML = `
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="section-eyebrow">Şirket Ağı</div>
                    <h2 class="mt-2 section-title">Kayıtlı Şirketler</h2>
                </div>
                <span class="badge-pill">${companies.length} şirket</span>
            </div>
            <div class="mt-5 list-stack">
                ${companies.length ? companies.map((company, index) => `
                    <article class="data-card animate-stagger-${(index % 3) + 1}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-base font-semibold">${escapeHtml(company.name)}</div>
                                <div class="mt-1 text-xs" style="color:var(--text-muted)">${escapeHtml(company.address || '—')}</div>
                            </div>
                            <span class="badge-pill badge-pill--info">${company.appointment_count} randevu</span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2.5 text-sm">
                            <div class="list-card">
                                <div class="text-xs" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.08em">Randevu</div>
                                <div class="mt-2 font-bold text-lg" data-counter="${company.appointment_count}">0</div>
                            </div>
                            <div class="list-card">
                                <div class="text-xs" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.08em">Dükkan</div>
                                <div class="mt-2">${limitBadge(company.shop_count, company.max_shop_count)}</div>
                            </div>
                            <div class="list-card">
                                <div class="text-xs" style="color:var(--text-subtle);text-transform:uppercase;letter-spacing:0.08em">Stüdyo</div>
                                <div class="mt-2">${limitBadge(company.studio_count, company.max_studio_count)}</div>
                            </div>
                        </div>
                        <form class="mt-5 form-grid" data-company-edit-form data-company-id="${company.id}">
                            <div class="field-wrap"><label class="field-label">Şirket Adı</label><input class="field-input" name="name" value="${escapeHtml(company.name)}"></div>
                            <div class="field-wrap"><label class="field-label">Adres</label><input class="field-input" name="address" value="${escapeHtml(company.address || '')}"></div>
                            <div class="form-grid form-grid--split">
                                <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="phone" value="${escapeHtml(company.phone || '')}"></div>
                                <div class="field-wrap"><label class="field-label">E-posta</label><input class="field-input" name="email" type="email" value="${escapeHtml(company.email || '')}"></div>
                            </div>
                            <div class="form-grid form-grid--split">
                                <div class="field-wrap"><label class="field-label">Max Dükkan <span style="color:var(--text-muted)">(0 = sınırsız)</span></label><input class="field-input" type="number" min="0" name="max_shop_count" value="${company.max_shop_count}"></div>
                                <div class="field-wrap"><label class="field-label">Max Stüdyo <span style="color:var(--text-muted)">(0 = sınırsız)</span></label><input class="field-input" type="number" min="0" name="max_studio_count" value="${company.max_studio_count}"></div>
                            </div>
                            <button class="button-primary mt-1" type="submit">Kaydet</button>
                        </form>
                    </article>
                `).join('') : '<div class="empty-state">Kayıtlı şirket bulunamadı.</div>'}
            </div>
        `;

        animateCounters(listNode);

        listNode.querySelectorAll('[data-company-edit-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                handleAsync(async () => {
                    const body = Object.fromEntries(new FormData(form).entries());
                    await apiFetch(`/companies/${form.getAttribute('data-company-id')}`, { method: 'PATCH', body });
                    showToast('Şirket güncellendi.', 'success');
                    await renderCompanies();
                });
            });
        });
    };

    await renderCompanies();

    qs('[data-company-create-form]', root)?.addEventListener('submit', (event) => {
        event.preventDefault();
        handleAsync(async () => {
            const form = event.target;
            const body = Object.fromEntries(new FormData(form).entries());
            await apiFetch('/companies', { method: 'POST', body });
            showToast('Şirket oluşturuldu.', 'success');
            form.reset();
            await renderCompanies();
        });
    });
};

/* ── Sayfa yönlendirici ────────────────────────────────────── */

const pageInitializers = [
    ['[data-admin-dashboard]',    renderDashboard],
    ['[data-admin-companies]',    renderCompaniesPage],
    ['[data-admin-users]',        renderUsersPage],
    ['[data-admin-appointments]', renderAppointmentsPage],
    ['[data-admin-studios]',      renderStudiosPage],
    ['[data-admin-shops]',        renderShopsPage],
];

document.addEventListener('DOMContentLoaded', () => {
    pageInitializers.forEach(([selector, initializer]) => {
        const root = qs(selector);
        if (!root) return;
        handleAsync(() => initializer(root), 'Panel verileri yüklenemedi.');
    });
});
