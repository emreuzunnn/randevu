import './bootstrap';

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
    apiBase: meta('admin-api-base') || '/api',
    token: meta('admin-api-token'),
    role: meta('admin-user-role'),
    canManageStructure: meta('admin-can-manage-structure') === '1',
    isAdmin: meta('admin-is-admin') === '1',
};

const toastRoot = () => qs('#admin-toast-root');

const showToast = (message, type = 'info') => {
    const root = toastRoot();

    if (!root) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.innerHTML = `
        <div class="text-sm font-semibold">${type === 'error' ? 'Islem basarisiz' : 'Bilgi'}</div>
        <div class="mt-1 text-sm text-slate-300">${escapeHtml(message)}</div>
    `;

    root.appendChild(toast);

    window.setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-6px)';
        window.setTimeout(() => toast.remove(), 240);
    }, 3400);
};

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
            'Beklenmeyen bir hata olustu.';

        throw new Error(errorMessage);
    }

    return payload;
};

const formatDate = (value) => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
};

const formatDateTime = (value) => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('tr-TR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const statusClass = (status) => {
    const map = {
        completed: 'badge-pill badge-pill--success',
        confirmed: 'badge-pill badge-pill--info',
        pending: 'badge-pill badge-pill--warning',
        cancelled: 'badge-pill badge-pill--danger',
        rescheduled: 'badge-pill badge-pill--warning',
        working: 'badge-pill badge-pill--success',
        break: 'badge-pill badge-pill--warning',
        transfer: 'badge-pill badge-pill--info',
        active: 'badge-pill badge-pill--success',
    };

    return map[status] || 'badge-pill';
};

const skeletonGrid = (count = 4) =>
    Array.from({ length: count }, () => '<div class="skeleton"></div>').join('');

const animateCounters = (scope = document) => {
    scope.querySelectorAll('[data-counter]').forEach((node) => {
        const target = Number(node.getAttribute('data-counter') || '0');
        const duration = 700;
        const startTime = performance.now();

        const tick = (time) => {
            const progress = Math.min((time - startTime) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            node.textContent = Math.round(target * eased).toLocaleString('tr-TR');

            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };

        requestAnimationFrame(tick);
    });
};

const handleAsync = async (fn, fallbackMessage = 'Islem tamamlanamadi.') => {
    try {
        await fn();
    } catch (error) {
        showToast(error.message || fallbackMessage, 'error');
    }
};

const renderDashboard = async (root) => {
    root.innerHTML = `
        <section class="hero-card">
            <div class="section-eyebrow">Merkez panorama</div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-6 pb-3">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-semibold tracking-tight">Operasyonun nabzini tek bakista yakala.</h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-muted">
                        Canli metrikler, donemsel raporlar ve sahadaki hareketler tek akista birlesir. Dogru anda dogru karari vermen icin tum tablo tek yerde toplanir.
                    </p>
                </div>
                <div class="badge-pill">Anlik operasyon takibi</div>
            </div>
        </section>
        <section class="metric-grid" data-dashboard-metrics>${skeletonGrid(4)}</section>
        <section class="data-grid" data-dashboard-reports>${skeletonGrid(3)}</section>
        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="panel-card" data-dashboard-studios>${skeletonGrid(1)}</div>
            <div class="panel-card" data-dashboard-appointments>${skeletonGrid(1)}</div>
        </section>
    `;

    const payload = await apiFetch('/home');
    const data = payload.data;

    qs('[data-dashboard-metrics]', root).innerHTML = `
        ${[
            ['Toplam Randevu', data.summary.total_appointments, 'Tum periyot'],
            ['Iptal', data.summary.cancelled_appointments, 'Risk takibi'],
            ['Aktif Ekip', data.summary.active_staff_count, 'Canli personel'],
            ['Transfer', data.summary.transfer_count, 'Sofor gorevleri'],
        ].map(([label, value, helper], index) => `
            <article class="metric-card animate-stagger-${(index % 3) + 1}">
                <div class="text-sm text-muted">${label}</div>
                <div class="mt-3 text-4xl font-semibold" data-counter="${value}">0</div>
                <div class="mt-2 text-sm text-muted">${helper}</div>
            </article>
        `).join('')}
    `;

    qs('[data-dashboard-reports]', root).innerHTML = Object.values(data.reports || {}).map((report, index) => `
        <article class="data-card animate-stagger-${(index % 3) + 1}">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="section-title">${escapeHtml(report.label)} Rapor</div>
                    <div class="mt-1 text-sm text-muted">${escapeHtml(report.date_from)} - ${escapeHtml(report.date_to)}</div>
                </div>
                <span class="badge-pill badge-pill--info">Donem</span>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="list-card"><div class="text-muted">Toplam</div><div class="mt-2 text-2xl font-semibold" data-counter="${report.total_appointments}">0</div></div>
                <div class="list-card"><div class="text-muted">Tamamlandi</div><div class="mt-2 text-2xl font-semibold" data-counter="${report.completed_appointments}">0</div></div>
                <div class="list-card"><div class="text-muted">Iptal</div><div class="mt-2 text-2xl font-semibold" data-counter="${report.cancelled_appointments}">0</div></div>
                <div class="list-card"><div class="text-muted">Bekleyen</div><div class="mt-2 text-2xl font-semibold" data-counter="${report.pending_appointments}">0</div></div>
            </div>
        </article>
    `).join('');

    qs('[data-dashboard-studios]', root).innerHTML = `
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="section-eyebrow">Studyo ozeti</div>
                <h2 class="mt-2 section-title">Studyo performansi</h2>
            </div>
            <span class="badge-pill">${data.studios.length} lokasyon</span>
        </div>
        <div class="mt-5 table-shell">
            <table>
                <thead>
                    <tr>
                        <th>Studyo</th>
                        <th>Konum</th>
                        <th>Aktif ekip</th>
                        <th>Randevu</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.studios.map((studio) => `
                        <tr>
                            <td class="font-semibold">${escapeHtml(studio.name)}</td>
                            <td>${escapeHtml(studio.location || '-')}</td>
                            <td>${studio.active_staff_count}</td>
                            <td>${studio.appointments_count}</td>
                        </tr>
                    `).join('') || '<tr><td colspan="4">Studyo bulunamadi.</td></tr>'}
                </tbody>
            </table>
        </div>
    `;

    qs('[data-dashboard-appointments]', root).innerHTML = `
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="section-eyebrow">Gunluk akis</div>
                <h2 class="mt-2 section-title">Bugunun randevulari</h2>
            </div>
            <span class="badge-pill badge-pill--warning">${data.today_appointments.length} kayit</span>
        </div>
        <div class="mt-5 list-stack">
            ${data.today_appointments.map((appointment) => `
                <div class="list-card">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold">${escapeHtml(`${appointment.customer.first_name} ${appointment.customer.last_name}`)}</div>
                            <div class="mt-1 text-sm text-muted">${escapeHtml(appointment.customer.hotel_name || appointment.studio || '-')}</div>
                            <div class="mt-2 text-sm text-muted">${formatDateTime(appointment.appointment_at)}</div>
                        </div>
                        <span class="${statusClass(appointment.status)}">${escapeHtml(appointment.status)}</span>
                    </div>
                </div>
            `).join('') || '<div class="empty-state">Bugun icin randevu bulunmuyor.</div>'}
        </div>
    `;

    animateCounters(root);
};

const renderUsersPage = async (root) => {
    root.innerHTML = `
        <section class="hero-card">
            <div class="section-eyebrow">Ekip yonetimi</div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-6">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-semibold tracking-tight">Dogru ekibi dogru studyoya hizla yerlestir.</h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-muted">
                        Personel listesi, roller ve durum bilgileri tek panelde gorunur. Ekip duzenini bozmadan hizli guncelleme yapabilirsin.
                    </p>
                </div>
                <div class="badge-pill badge-pill--success">Ekip yonetimi</div>
            </div>
        </section>
        <section class="grid gap-6 xl:grid-cols-[1.08fr_0.92fr]">
            <div class="panel-card">
                <div class="action-row">
                    <div class="field-wrap min-w-[240px] flex-1">
                        <label class="field-label">Studyo sec</label>
                        <select class="field-select" data-users-studio-select></select>
                    </div>
                    <button class="button-secondary" data-users-refresh>Listeyi yenile</button>
                </div>
                <div class="mt-5 list-stack" data-users-list>${skeletonGrid(4)}</div>
            </div>
            <div class="form-shell">
                <div class="section-eyebrow">Yeni personel</div>
                <h2 class="mt-2 section-title">Kullanici ekle</h2>
                <form class="mt-5 form-grid" data-users-create-form>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Isim</label><input class="field-input" name="name" required></div>
                        <div class="field-wrap"><label class="field-label">Soyad</label><input class="field-input" name="surname" required></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="phone" required></div>
                        <div class="field-wrap"><label class="field-label">Mail</label><input class="field-input" name="email" type="email" required></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Rol</label><select class="field-select" name="role" data-users-role-select></select></div>
                        <div class="field-wrap"><label class="field-label">Studyo</label><select class="field-select" name="studio_id" data-users-create-studio></select></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Sifre</label><input class="field-input" name="password" type="password" required></div>
                        <div class="field-wrap"><label class="field-label">Sifre tekrar</label><input class="field-input" name="password_confirmation" type="password" required></div>
                    </div>
                    <button class="button-primary" type="submit">Kullaniciyi olustur</button>
                </form>
            </div>
        </section>
    `;

    const studioSelect = qs('[data-users-studio-select]', root);
    const createStudioSelect = qs('[data-users-create-studio]', root);
    const listNode = qs('[data-users-list]', root);
    const form = qs('[data-users-create-form]', root);
    const roleSelect = qs('[data-users-role-select]', root);

    const roles = adminConfig.isAdmin
        ? ['admin', 'yonetici', 'supervisor', 'sofor', 'calisan']
        : ['supervisor', 'sofor', 'calisan'];

    roleSelect.innerHTML = roles.map((role) => `<option value="${role}">${role}</option>`).join('');

    const loadStudios = async () => {
        const payload = await apiFetch('/studios/options');
        const studios = payload.data || [];
        const options = studios.map((studio) => `<option value="${studio.id}">${escapeHtml(studio.name)}</option>`).join('');
        studioSelect.innerHTML = options;
        createStudioSelect.innerHTML = options;
        return studios;
    };

    const renderUsers = async () => {
        if (!studioSelect.value) {
            listNode.innerHTML = '<div class="empty-state">Once bir studyo sec.</div>';
            return;
        }

        listNode.innerHTML = skeletonGrid(4);
        const payload = await apiFetch(`/studios/${studioSelect.value}/users`);
        const users = payload.data || [];

        listNode.innerHTML = users.length
            ? users.map((user, index) => `
                <article class="data-card animate-stagger-${(index % 3) + 1}" data-user-card data-user-id="${user.id}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-lg font-semibold">${escapeHtml(user.name)}</div>
                            <div class="mt-1 text-sm text-muted">${escapeHtml(user.email)}</div>
                        </div>
                        <span class="${statusClass(user.status)}">${escapeHtml(user.status)}</span>
                    </div>
                    <div class="mt-5 form-grid form-grid--split">
                        <div class="field-wrap">
                            <label class="field-label">Rol</label>
                            <select class="field-select" data-user-role>
                                ${roles.map((role) => `<option value="${role}" ${user.role === role ? 'selected' : ''}>${role}</option>`).join('')}
                            </select>
                        </div>
                        <div class="field-wrap">
                            <label class="field-label">Durum</label>
                            <select class="field-select" data-user-status>
                                ${['working', 'break', 'transfer'].map((status) => `<option value="${status}" ${user.status === status ? 'selected' : ''}>${status}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 action-row">
                        <label class="inline-flex items-center gap-2 text-sm text-muted">
                            <input type="checkbox" data-user-active ${user.is_active ? 'checked' : ''}>
                            Aktif
                        </label>
                        <button class="button-secondary" data-user-save>Kullaniciyi guncelle</button>
                    </div>
                </article>
            `).join('')
            : '<div class="empty-state">Bu studyoda kullanici bulunmuyor.</div>';

        listNode.querySelectorAll('[data-user-save]').forEach((button) => {
            button.addEventListener('click', () => handleAsync(async () => {
                const card = button.closest('[data-user-card]');
                const userId = card?.getAttribute('data-user-id');

                await apiFetch(`/studios/${studioSelect.value}/users/${userId}`, {
                    method: 'PATCH',
                    body: {
                        role: qs('[data-user-role]', card)?.value,
                        status: qs('[data-user-status]', card)?.value,
                        is_active: qs('[data-user-active]', card)?.checked,
                    },
                });

                showToast('Kullanici guncellendi.', 'success');
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
            showToast('Yeni kullanici eklendi.', 'success');
            await renderUsers();
        });
    });
};

const renderAppointmentsPage = async (root) => {
    root.innerHTML = `
        <section class="hero-card">
            <div class="section-eyebrow">Randevu akisi</div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-6">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-semibold tracking-tight">Her randevuyu duzenli, hizli ve kontrollu ilerlet.</h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-muted">
                        Liste, olusturma ve durum guncelleme akislarini tek merkezde toplar. Ekibin bir sonraki adimi her an net gorunur.
                    </p>
                </div>
                <div class="badge-pill badge-pill--warning">Canli operasyon akisi</div>
            </div>
        </section>
        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="panel-card">
                <div class="action-row">
                    <div class="field-wrap min-w-[240px] flex-1">
                        <label class="field-label">Studyo sec</label>
                        <select class="field-select" data-appointments-studio-select></select>
                    </div>
                    <button class="button-secondary" data-appointments-refresh>Akisi yenile</button>
                </div>
                <div class="mt-5 list-stack" data-appointments-list>${skeletonGrid(4)}</div>
            </div>
            <div class="form-shell">
                <div class="section-eyebrow">Yeni randevu</div>
                <h2 class="mt-2 section-title">Randevu olustur</h2>
                <form class="mt-5 form-grid" data-appointment-form>
                    <div class="field-wrap"><label class="field-label">Studyo</label><select class="field-select" name="studio_id" data-appointment-studio></select></div>
                    <div class="field-wrap"><label class="field-label">Fis / gorsel yolu</label><input class="field-input" name="source_image_path"></div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Ad</label><input class="field-input" name="first_name" required></div>
                        <div class="field-wrap"><label class="field-label">Soyad</label><input class="field-input" name="last_name" required></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="phone_number"></div>
                        <div class="field-wrap"><label class="field-label">Ulke kodu</label><input class="field-input" name="phone_country_code" value="+90"></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Otel</label><input class="field-input" name="hotel_name"></div>
                        <div class="field-wrap"><label class="field-label">Oda no</label><input class="field-input" name="room_number"></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Kisi sayisi</label><input class="field-input" type="number" min="1" name="pax" required></div>
                        <div class="field-wrap"><label class="field-label">Tip</label><input class="field-input" name="appointment_type" value="standard"></div>
                    </div>
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Tarih</label><input class="field-input" type="date" name="date" required></div>
                        <div class="field-wrap"><label class="field-label">Saat</label><input class="field-input" type="time" name="time" required></div>
                    </div>
                    <div class="field-wrap"><label class="field-label">Yer</label><input class="field-input" name="place"></div>
                    <div class="field-wrap"><label class="field-label">Surucu</label><select class="field-select" name="assigned_driver_user_id" data-driver-select><option value="">Surucu sec</option></select></div>
                    <div class="field-wrap"><label class="field-label">Not</label><textarea class="field-textarea" rows="3" name="notes"></textarea></div>
                    <button class="button-primary" type="submit">Randevuyu kaydet</button>
                </form>
            </div>
        </section>
    `;

    const studioSelect = qs('[data-appointments-studio-select]', root);
    const createStudioSelect = qs('[data-appointment-studio]', root);
    const listNode = qs('[data-appointments-list]', root);
    const form = qs('[data-appointment-form]', root);
    const driverSelect = qs('[data-driver-select]', root);

    const loadStudios = async () => {
        const payload = await apiFetch('/studios/options');
        const studios = payload.data || [];
        const options = studios.map((studio) => `<option value="${studio.id}">${escapeHtml(studio.name)}</option>`).join('');
        studioSelect.innerHTML = options;
        createStudioSelect.innerHTML = options;
    };

    const loadSupport = async (studioId) => {
        if (!studioId) return;
        const payload = await apiFetch(`/studios/${studioId}/appointment-support`);
        const drivers = payload.data?.drivers || [];
        driverSelect.innerHTML = `<option value="">Surucu sec</option>${drivers.map((driver) => `
            <option value="${driver.id}">${escapeHtml(driver.name)}${driver.phone ? ` | ${escapeHtml(driver.phone)}` : ''}</option>
        `).join('')}`;
    };

    const renderAppointments = async () => {
        if (!studioSelect.value) {
            listNode.innerHTML = '<div class="empty-state">Randevulari gormek icin bir studyo sec.</div>';
            return;
        }

        listNode.innerHTML = skeletonGrid(4);
        const payload = await apiFetch(`/studios/${studioSelect.value}/appointments`);
        const appointments = payload.data || [];

        listNode.innerHTML = appointments.length
            ? appointments.map((appointment, index) => `
                <article class="data-card animate-stagger-${(index % 3) + 1}" data-appointment-id="${appointment.id}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-lg font-semibold">${escapeHtml(`${appointment.customer.first_name} ${appointment.customer.last_name}`)}</div>
                            <div class="mt-1 text-sm text-muted">${escapeHtml(appointment.customer.hotel_name || appointment.studio || '-')}</div>
                            <div class="mt-2 text-sm text-muted">${formatDateTime(appointment.appointment_at)}</div>
                        </div>
                        <span class="${statusClass(appointment.status)}">${escapeHtml(appointment.status)}</span>
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="field-wrap">
                            <label class="field-label">Durum</label>
                            <select class="field-select" data-appointment-status>
                                ${['pending', 'confirmed', 'completed', 'cancelled', 'rescheduled'].map((status) => `
                                    <option value="${status}" ${appointment.status === status ? 'selected' : ''}>${status}</option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="field-wrap">
                            <label class="field-label">Surucu</label>
                            <select class="field-select" data-appointment-driver>
                                <option value="">Surucu sec</option>
                                ${Array.from(driverSelect.options).map((option) => `
                                    <option value="${option.value}" ${String(appointment.assigned_driver_user_id || '') === option.value ? 'selected' : ''}>${escapeHtml(option.textContent || '')}</option>
                                `).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 action-row">
                        <a href="/admin/appointments/${appointment.id}" class="button-ghost">Detay</a>
                        <button class="button-secondary" data-appointment-save>Durumu kaydet</button>
                    </div>
                </article>
            `).join('')
            : '<div class="empty-state">Bu studyoda randevu bulunmuyor.</div>';

        listNode.querySelectorAll('[data-appointment-save]').forEach((button) => {
            button.addEventListener('click', () => handleAsync(async () => {
                const card = button.closest('[data-appointment-id]');
                const appointmentId = card?.getAttribute('data-appointment-id');
                await apiFetch(`/studios/${studioSelect.value}/appointments/${appointmentId}`, {
                    method: 'PATCH',
                    body: {
                        status: qs('[data-appointment-status]', card)?.value,
                        assigned_driver_user_id: qs('[data-appointment-driver]', card)?.value || null,
                    },
                });
                showToast('Randevu guncellendi.', 'success');
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
                    first_name: formData.get('first_name'),
                    last_name: formData.get('last_name'),
                    phone_country_code: formData.get('phone_country_code') || null,
                    phone_number: formData.get('phone_number') || null,
                    hotel_name: formData.get('hotel_name') || null,
                    room_number: formData.get('room_number') || null,
                },
                pax: Number(formData.get('pax')),
                appointment_at: `${formData.get('date')} ${formData.get('time')}:00`,
                appointment_type: formData.get('appointment_type') || 'standard',
                notes: formData.get('notes') || null,
                source_image_path: formData.get('source_image_path') || null,
                assigned_driver_user_id: formData.get('assigned_driver_user_id') || null,
            };

            await apiFetch(`/studios/${formData.get('studio_id')}/appointments`, {
                method: 'POST',
                body,
            });

            showToast('Randevu olusturuldu.', 'success');
            form.reset();
            await loadSupport(createStudioSelect.value);
            await renderAppointments();
        });
    });
};

const renderStudiosPage = async (root) => {
    root.innerHTML = `
        <section class="hero-card mb-4">
            <div class="section-eyebrow">Studyo yonetimi</div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-6">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-semibold tracking-tight">Her studyoyu net hedeflerle ve guclu bir gorunumle yonet.</h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-muted">
                        Lokasyon, ekip yogunlugu ve ayar bilgileri tek kartta toplanir. Boylece her studyonun durumu ilk bakista anlasilir.
                    </p>
                </div>
                <div class="badge-pill">Lokasyon kontrolu</div>
            </div>
        </section>
        <section class="data-grid" data-studios-grid>${skeletonGrid(3)}</section>
    `;

    const grid = qs('[data-studios-grid]', root);
    const payload = await apiFetch('/studios/overview');
    const studios = payload.data || [];

    grid.innerHTML = studios.length
        ? studios.map((studio, index) => `
            <article class="data-card animate-stagger-${(index % 3) + 1}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="section-eyebrow">${escapeHtml(studio.shop?.name || 'Dukkan bilgisi yok')}</div>
                        <h2 class="mt-2 text-2xl font-semibold">${escapeHtml(studio.name)}</h2>
                    </div>
                    <span class="badge-pill badge-pill--info">${studio.appointments_count} randevu</span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="list-card"><div class="text-muted">Konum</div><div class="mt-2 font-semibold">${escapeHtml(studio.location || '-')}</div></div>
                    <div class="list-card"><div class="text-muted">Aktif ekip</div><div class="mt-2 font-semibold">${studio.active_staff_count}</div></div>
                </div>
                <form class="mt-5 form-grid" data-studio-form data-studio-id="${studio.id}">
                    <div class="field-wrap"><label class="field-label">Studyo adi</label><input class="field-input" name="name" value="${escapeHtml(studio.name)}"></div>
                    <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location" value="${escapeHtml(studio.location || '')}"></div>
                    <div class="field-wrap"><label class="field-label">Logo yolu</label><input class="field-input" name="logo_path" value="${escapeHtml(studio.logo_path || '')}"></div>
                    <div class="field-wrap"><label class="field-label">Bildirim dakikasi</label><input class="field-input" type="number" min="0" name="notification_lead_minutes" value="${studio.notification_lead_minutes}"></div>
                    <button class="button-primary" type="submit">Studyo ayarlarini kaydet</button>
                </form>
            </article>
        `).join('')
        : '<div class="empty-state">Erisilebilir studyo bulunmuyor.</div>';

    grid.querySelectorAll('[data-studio-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            handleAsync(async () => {
                const formData = new FormData(form);
                await apiFetch(`/studios/${form.getAttribute('data-studio-id')}`, {
                    method: 'PATCH',
                    body: Object.fromEntries(formData.entries()),
                });
                showToast('Studyo kaydi guncellendi.', 'success');
                await renderStudiosPage(root);
            });
        });
    });
};

const renderShopsPage = async (root) => {
    root.innerHTML = `
        <section class="hero-card">
            <div class="section-eyebrow">Dukkan yonetimi</div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-6">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-semibold tracking-tight">Dukkanlarini tek markanin guclu subeleri gibi konumlandir.</h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-muted">
                        Dukkan kartlari, yonetici eslestirmesi ve buyume plani ayni yerde bulusur. Yapini buyuturken kontrolu elinde tutarsin.
                    </p>
                </div>
                <div class="badge-pill badge-pill--success">Dukkan yonetimi</div>
            </div>
        </section>
        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="panel-card" data-shops-list>${skeletonGrid(3)}</div>
            <div class="form-shell" data-shops-create></div>
        </section>
    `;

    const listNode = qs('[data-shops-list]', root);
    const createNode = qs('[data-shops-create]', root);

    const [shopsPayload, managersPayload] = await Promise.all([
        apiFetch('/shops'),
        adminConfig.isAdmin ? apiFetch('/users/options?roles=yonetici,supervisor') : Promise.resolve({ data: [] }),
    ]);

    const shops = shopsPayload.data || [];
    const managers = managersPayload.data || [];
    const buildManagerOptions = (selectedId = null) => `<option value="">Yonetici sec</option>${managers.map((manager) => `
        <option value="${manager.id}">${escapeHtml(manager.name)} | ${escapeHtml(manager.role)}</option>
    `).join('')}`.replace(`value="${selectedId}"`, `value="${selectedId}" selected`);

    listNode.innerHTML = `
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="section-eyebrow">Dukkan agi</div>
                <h2 class="mt-2 section-title">Aktif dukkanlar</h2>
            </div>
            <span class="badge-pill">${shops.length} dukkan</span>
        </div>
        <div class="mt-5 list-stack">
            ${shops.map((shop, index) => `
                <article class="data-card animate-stagger-${(index % 3) + 1}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xl font-semibold">${escapeHtml(shop.name)}</div>
                            <div class="mt-1 text-sm text-muted">${escapeHtml(shop.location || '-')}</div>
                        </div>
                        <span class="${shop.is_active ? 'badge-pill badge-pill--success' : 'badge-pill badge-pill--danger'}">${shop.is_active ? 'aktif' : 'pasif'}</span>
                    </div>
                    <div class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                        <div class="list-card">
                            <div class="text-muted">Yonetici</div>
                            <div class="mt-2 font-semibold">${escapeHtml(shop.manager?.name || '-')}</div>
                        </div>
                        <div class="list-card">
                            <div class="text-muted">Bagli studyolar</div>
                            <div class="mt-2 font-semibold">${shop.studios.map((studio) => escapeHtml(studio.name)).join(', ') || '-'}</div>
                        </div>
                    </div>
                    <form class="mt-5 form-grid" data-shop-form data-shop-id="${shop.id}">
                        <div class="field-wrap"><label class="field-label">Dukkan adi</label><input class="field-input" name="name" value="${escapeHtml(shop.name)}"></div>
                        <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location" value="${escapeHtml(shop.location || '')}"></div>
                        ${adminConfig.isAdmin ? `
                            <div class="field-wrap">
                                <label class="field-label">Yonetici</label>
                                <select class="field-select" name="manager_user_id">
                                    ${buildManagerOptions(shop.manager?.id ?? null)}
                                </select>
                            </div>
                        ` : ''}
                        <button class="button-primary" type="submit">Dukkani kaydet</button>
                    </form>
                </article>
            `).join('') || '<div class="empty-state">Dukkan bulunmuyor.</div>'}
        </div>
    `;

    createNode.innerHTML = adminConfig.isAdmin
        ? `
            <div class="section-eyebrow">Yeni lokasyon</div>
            <h2 class="mt-2 section-title">Yeni dukkan olustur</h2>
                <form class="mt-5 form-grid" data-shop-create-form>
                    <div class="field-wrap"><label class="field-label">Dukkan adi</label><input class="field-input" name="name" required></div>
                    <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location"></div>
                    <div class="field-wrap"><label class="field-label">Yonetici</label><select class="field-select" name="manager_user_id">${buildManagerOptions()}</select></div>
                    <button class="button-primary" type="submit">Dukkani olustur</button>
                </form>
            `
        : `
            <div class="empty-state">
                <div class="section-title">Dukkan bilgileri senkronize.</div>
                <div class="mt-3">Bu alanda sadece sana ait dukkan kartlari listelenir.</div>
            </div>
        `;

    listNode.querySelectorAll('[data-shop-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            handleAsync(async () => {
                const body = Object.fromEntries(new FormData(form).entries());
                await apiFetch(`/shops/${form.getAttribute('data-shop-id')}`, {
                    method: 'PATCH',
                    body,
                });
                showToast('Dukkan guncellendi.', 'success');
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
                await apiFetch('/shops', {
                    method: 'POST',
                    body,
                });
                showToast('Yeni dukkan eklendi.', 'success');
                await renderShopsPage(root);
            });
        });
    }
};

const pageInitializers = [
    ['[data-admin-dashboard]', renderDashboard],
    ['[data-admin-users]', renderUsersPage],
    ['[data-admin-appointments]', renderAppointmentsPage],
    ['[data-admin-studios]', renderStudiosPage],
    ['[data-admin-shops]', renderShopsPage],
];

document.addEventListener('DOMContentLoaded', () => {
    pageInitializers.forEach(([selector, initializer]) => {
        const root = qs(selector);
        if (!root) return;

        handleAsync(() => initializer(root), 'Panel verileri yuklenemedi.');
    });
});
