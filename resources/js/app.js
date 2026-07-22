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
    const depositWrap = form.querySelector('[data-deposit-field]');
    const depositInput = form.querySelector('[name="deposit_amount"]');
    const pickupWrap = form.querySelector('[data-pickup-field]');
    const pickupInput = form.querySelector('[name="pickup_required"]');
    const imageLabel = form.querySelector('[data-appointment-images-label]');

    const sync = () => {
        const isDesigner = typeSelect.value === 'designer';
        if (priceWrap) priceWrap.style.display = isDesigner ? 'none' : '';
        if (priceInput && isDesigner) priceInput.value = '';
        if (depositWrap) depositWrap.style.display = isDesigner ? 'none' : '';
        if (depositInput && isDesigner) depositInput.value = '';
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

const assignmentStatusLabel = (appointment = {}) => {
    const hasAssignee = appointment.assigned_artist_user_id || appointment.artist;
    if (!hasAssignee || ['completed', 'cancelled'].includes(appointment.status)) return '';
    if (appointment.artist_status === 'pending') return 'Atama Bekliyor';
    if (appointment.artist_status === 'accepted') return 'Atama Kabul Edildi';
    if (appointment.artist_status === 'rejected') return 'Atama Reddedildi';
    return '';
};

const assignmentStatusClass = (appointment = {}) => {
    if (appointment.artist_status === 'accepted') return 'badge-pill badge-pill--success';
    if (appointment.artist_status === 'rejected') return 'badge-pill badge-pill--danger';
    if (appointment.artist_status === 'pending') return 'badge-pill badge-pill--warning';
    return 'badge-pill';
};

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

const optimizeImageForUpload = (file, maxSize = 1200, quality = 0.9) => new Promise((resolve) => {
    if (!(file instanceof File) || !file.type.startsWith('image/') || file.type === 'image/gif') {
        resolve(file);
        return;
    }

    const image = new Image();
    const objectUrl = URL.createObjectURL(file);
    image.onload = () => {
        URL.revokeObjectURL(objectUrl);

        const scale = Math.min(1, maxSize / Math.max(image.width, image.height));
        if (scale >= 1 && file.size <= 900 * 1024) {
            resolve(file);
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.width * scale));
        canvas.height = Math.max(1, Math.round(image.height * scale));
        const context = canvas.getContext('2d');
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => {
            if (!blob) {
                resolve(file);
                return;
            }

            const optimizedName = file.name.replace(/\.[^.]+$/, '') + '.webp';
            resolve(new File([blob], optimizedName, { type: 'image/webp', lastModified: Date.now() }));
        }, 'image/webp', quality);
    };
    image.onerror = () => {
        URL.revokeObjectURL(objectUrl);
        resolve(file);
    };
    image.src = objectUrl;
});

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

/* ── Bilet PDF demo şablonu ─────────────────────────────────── */

const TICKET_PDF_TEMPLATE_KEY = 'tattodesk.ticketPdfTemplate.v1';

const DEFAULT_TICKET_PDF_TEMPLATE = {
    language: 'de',
    logoUrl: '',
    brandTitle: 'SOUL OF INK',
    brandSubtitle: 'TATTOO & PIERCING',
    brandTagline: 'THE ART OF TRUST, THE MARK OF QUALITY',
    footer: {
        email: 'inksoulof@gmail.com',
        phone: '+90 545 424 37 39',
        address: 'Gündoğdu Mahallesi 18.Sokak No:8',
        instagram: 'soulofink.gundogdu',
        facebook: 'soulofink.gundogdu',
    },
    translations: {
        de: {
            name: 'Germany (+49)',
            labels: {
                documentDate: 'Datum des Dokuments',
                ticketCode: 'Ticketcode',
                customerName: 'Vorname Familienname',
                phone: 'Telefon',
                hotelRoom: 'Hotel / Zimmernummer',
                ticketType: 'Art des Tickets',
                infoStaff: 'Info -Mitarbeiter',
                reservationDate: 'Reservierungsdatum',
                reservationTime: 'Reservierungszeit',
                pickup: 'Abholung',
                quantity: 'Menge',
                deposit: 'Kaution',
                remaining: 'Rest',
                artist: 'Tattoo-Künstler',
                signature: 'UNTERSCHRIFT',
            },
            contractText: [
                '1. Die Anzahlung wird geleistet, um den Termin des Kunden zu sichern. Bei Absage oder Nichterscheinen des Kunden kann die Anzahlung nicht zurückerstattet werden.',
                '2. Sollte der Kunde den Termin verschieben wollen, muss er das Studio mindestens 24 Stunden vorher informieren. Andernfalls kann eine neue Anzahlung erforderlich sein.',
                '3. Das Studio ist für Änderungen am Entwurf, die medizinische Eignung und die endgültige Durchführung berechtigt, notwendige Hinweise zu geben.',
            ].join('\n'),
            acceptanceText: 'Der Kunde akzeptiert die oben genannten Informationen und Bedingungen.',
            receiptText: 'Diese Quittung wird für die Reservierung und Zahlung ausgestellt.',
            confirmationText: 'Mit seiner Unterschrift bestätigt der Kunde die Richtigkeit der Angaben.',
        },
        tr: {
            name: 'Turkey (+90)',
            labels: {
                documentDate: 'Belge Tarihi',
                ticketCode: 'Bilet Kodu',
                customerName: 'Ad Soyad',
                phone: 'Telefon',
                hotelRoom: 'Otel / Oda Numarası',
                ticketType: 'Bilet Türü',
                infoStaff: 'Info Personeli',
                reservationDate: 'Rezervasyon Tarihi',
                reservationTime: 'Rezervasyon Saati',
                pickup: 'Transfer',
                quantity: 'Kişi',
                deposit: 'Depozito',
                remaining: 'Kalan',
                artist: 'Dövme Sanatçısı',
                signature: 'İMZA',
            },
            contractText: [
                '1. Alınan depozito müşterinin randevu saatini güvence altına almak içindir. Müşteri iptal eder veya gelmezse depozito iade edilmez.',
                '2. Müşteri randevusunu değiştirmek isterse stüdyoya en az 24 saat önce bilgi vermelidir. Aksi durumda yeni depozito istenebilir.',
                '3. Stüdyo tasarım değişikliği, sağlık uygunluğu ve işlem planı hakkında gerekli yönlendirmeleri yapma hakkına sahiptir.',
            ].join('\n'),
            acceptanceText: 'Müşteri yukarıdaki bilgi ve koşulları kabul eder.',
            receiptText: 'Bu belge rezervasyon ve ödeme kaydı için düzenlenmiştir.',
            confirmationText: 'Müşteri imzası ile bilgilerin doğruluğunu onaylar.',
        },
        en: {
            name: 'United Kingdom (+44)',
            labels: {
                documentDate: 'Document Date',
                ticketCode: 'Ticket Code',
                customerName: 'Full Name',
                phone: 'Phone',
                hotelRoom: 'Hotel / Room Number',
                ticketType: 'Ticket Type',
                infoStaff: 'Info Staff',
                reservationDate: 'Reservation Date',
                reservationTime: 'Reservation Time',
                pickup: 'Pickup',
                quantity: 'Quantity',
                deposit: 'Deposit',
                remaining: 'Remaining',
                artist: 'Tattoo Artist',
                signature: 'SIGNATURE',
            },
            contractText: [
                '1. The deposit is collected to secure the customer appointment. If the customer cancels or does not attend, the deposit is non-refundable.',
                '2. If the customer wants to reschedule, the studio must be informed at least 24 hours before the appointment. Otherwise, a new deposit may be required.',
                '3. The studio may provide necessary guidance regarding design changes, medical suitability and the final procedure plan.',
            ].join('\n'),
            acceptanceText: 'The customer accepts the information and conditions above.',
            receiptText: 'This receipt is issued for reservation and payment records.',
            confirmationText: 'By signing, the customer confirms that the information is correct.',
        },
    },
};

Object.entries({
    pl: {
        name: 'Poland (+48)',
        labels: {
            documentDate: 'Data dokumentu',
            ticketCode: 'Kod biletu',
            customerName: 'Imię i nazwisko',
            phone: 'Telefon',
            hotelRoom: 'Hotel / Numer pokoju',
            ticketType: 'Rodzaj biletu',
            infoStaff: 'Pracownik informacji',
            reservationDate: 'Data rezerwacji',
            reservationTime: 'Godzina rezerwacji',
            pickup: 'Odbiór',
            quantity: 'Liczba osób',
            deposit: 'Zaliczka',
            remaining: 'Pozostało',
            artist: 'Tatuażysta',
            signature: 'PODPIS',
        },
    },
    nl: {
        name: 'Netherlands (+31)',
        labels: {
            documentDate: 'Documentdatum',
            ticketCode: 'Ticketcode',
            customerName: 'Volledige naam',
            phone: 'Telefoon',
            hotelRoom: 'Hotel / Kamernummer',
            ticketType: 'Tickettype',
            infoStaff: 'Infomedewerker',
            reservationDate: 'Reserveringsdatum',
            reservationTime: 'Reserveringstijd',
            pickup: 'Ophalen',
            quantity: 'Aantal',
            deposit: 'Aanbetaling',
            remaining: 'Resterend',
            artist: 'Tatoeëerder',
            signature: 'HANDTEKENING',
        },
    },
    ru: {
        name: 'Russia (+7)',
        labels: {
            documentDate: 'Дата документа',
            ticketCode: 'Код билета',
            customerName: 'Имя и фамилия',
            phone: 'Телефон',
            hotelRoom: 'Отель / номер комнаты',
            ticketType: 'Тип билета',
            infoStaff: 'Инфо-сотрудник',
            reservationDate: 'Дата бронирования',
            reservationTime: 'Время бронирования',
            pickup: 'Трансфер',
            quantity: 'Количество',
            deposit: 'Депозит',
            remaining: 'Остаток',
            artist: 'Тату-мастер',
            signature: 'ПОДПИСЬ',
        },
    },
    ch: {
        name: 'Switzerland (+41)',
        labels: DEFAULT_TICKET_PDF_TEMPLATE.translations.de.labels,
    },
    be: {
        name: 'Belgium (+32)',
        labels: {
            documentDate: 'Date du document',
            ticketCode: 'Code du ticket',
            customerName: 'Nom complet',
            phone: 'Téléphone',
            hotelRoom: 'Hôtel / Numéro de chambre',
            ticketType: 'Type de ticket',
            infoStaff: 'Personnel info',
            reservationDate: 'Date de réservation',
            reservationTime: 'Heure de réservation',
            pickup: 'Prise en charge',
            quantity: 'Quantité',
            deposit: 'Acompte',
            remaining: 'Reste',
            artist: 'Tatoueur',
            signature: 'SIGNATURE',
        },
    },
    et: {
        name: 'Estonia (+372)',
        labels: {
            documentDate: 'Dokumendi kuupäev',
            ticketCode: 'Pileti kood',
            customerName: 'Täisnimi',
            phone: 'Telefon',
            hotelRoom: 'Hotell / toa number',
            ticketType: 'Pileti tüüp',
            infoStaff: 'Infotöötaja',
            reservationDate: 'Broneeringu kuupäev',
            reservationTime: 'Broneeringu kellaaeg',
            pickup: 'Vastuvõtt',
            quantity: 'Kogus',
            deposit: 'Tagatisraha',
            remaining: 'Jääk',
            artist: 'Tätoveerija',
            signature: 'ALLKIRI',
        },
    },
    sv: {
        name: 'Sweden (+46)',
        labels: {
            documentDate: 'Dokumentdatum',
            ticketCode: 'Biljettkod',
            customerName: 'Fullständigt namn',
            phone: 'Telefon',
            hotelRoom: 'Hotell / rumsnummer',
            ticketType: 'Biljettyp',
            infoStaff: 'Infopersonal',
            reservationDate: 'Bokningsdatum',
            reservationTime: 'Bokningstid',
            pickup: 'Upphämtning',
            quantity: 'Antal',
            deposit: 'Deposition',
            remaining: 'Återstår',
            artist: 'Tatuerare',
            signature: 'SIGNATUR',
        },
    },
    no: {
        name: 'Norway (+47)',
        labels: {
            documentDate: 'Dokumentdato',
            ticketCode: 'Billettkode',
            customerName: 'Fullt navn',
            phone: 'Telefon',
            hotelRoom: 'Hotell / romnummer',
            ticketType: 'Billettype',
            infoStaff: 'Infopersonale',
            reservationDate: 'Reservasjonsdato',
            reservationTime: 'Reservasjonstid',
            pickup: 'Henting',
            quantity: 'Antall',
            deposit: 'Depositum',
            remaining: 'Gjenstår',
            artist: 'Tatovør',
            signature: 'SIGNATUR',
        },
    },
    da: {
        name: 'Denmark (+45)',
        labels: {
            documentDate: 'Dokumentdato',
            ticketCode: 'Billetkode',
            customerName: 'Fulde navn',
            phone: 'Telefon',
            hotelRoom: 'Hotel / værelsesnummer',
            ticketType: 'Billettype',
            infoStaff: 'Infomedarbejder',
            reservationDate: 'Reservationsdato',
            reservationTime: 'Reservationstid',
            pickup: 'Afhentning',
            quantity: 'Antal',
            deposit: 'Depositum',
            remaining: 'Resterende',
            artist: 'Tatovør',
            signature: 'UNDERSKRIFT',
        },
    },
    fi: {
        name: 'Finland (+358)',
        labels: {
            documentDate: 'Asiakirjan päivämäärä',
            ticketCode: 'Lipun koodi',
            customerName: 'Koko nimi',
            phone: 'Puhelin',
            hotelRoom: 'Hotelli / huonenumero',
            ticketType: 'Lipun tyyppi',
            infoStaff: 'Infotyöntekijä',
            reservationDate: 'Varauspäivä',
            reservationTime: 'Varausaika',
            pickup: 'Nouto',
            quantity: 'Määrä',
            deposit: 'Varausmaksu',
            remaining: 'Jäljellä',
            artist: 'Tatuoija',
            signature: 'ALLEKIRJOITUS',
        },
    },
}).forEach(([language, item]) => {
    DEFAULT_TICKET_PDF_TEMPLATE.translations[language] = {
        ...JSON.parse(JSON.stringify(DEFAULT_TICKET_PDF_TEMPLATE.translations.en)),
        name: item.name,
        labels: item.labels,
    };
});

const cloneTicketPdfTemplate = () => JSON.parse(JSON.stringify(DEFAULT_TICKET_PDF_TEMPLATE));

const normalizeTicketPdfTemplate = (stored = {}) => {
    const template = cloneTicketPdfTemplate();
    Object.assign(template, stored || {});
    template.footer = { ...DEFAULT_TICKET_PDF_TEMPLATE.footer, ...(stored?.footer || {}) };
    template.translations = cloneTicketPdfTemplate().translations;
    Object.entries(stored?.translations || {}).forEach(([language, translation]) => {
        const defaultTranslation = template.translations[language] || { name: language.toUpperCase(), labels: DEFAULT_TICKET_PDF_TEMPLATE.translations.en.labels };
        template.translations[language] = {
            ...defaultTranslation,
            ...translation,
            labels: defaultTranslation.labels,
        };
    });
    return template;
};

const loadTicketPdfTemplate = () => {
    try {
        return normalizeTicketPdfTemplate(JSON.parse(localStorage.getItem(TICKET_PDF_TEMPLATE_KEY) || '{}'));
    } catch (error) {
        return cloneTicketPdfTemplate();
    }
};

const saveTicketPdfTemplate = (template) => {
    localStorage.setItem(TICKET_PDF_TEMPLATE_KEY, JSON.stringify(normalizeTicketPdfTemplate(template)));
};

const ticketPdfTemplateFromApi = (template = {}) => {
    const normalized = normalizeTicketPdfTemplate({
        language: template.default_language || template.language,
        logoUrl: template.logo_url || template.logoUrl,
        brandTitle: template.brand_title || template.brandTitle,
        brandSubtitle: template.brand_subtitle || template.brandSubtitle,
        brandTagline: template.brand_tagline || template.brandTagline,
        footer: template.footer,
        translations: Object.fromEntries(Object.entries(template.translations || {}).map(([language, item]) => [
            language,
            {
                name: item.name,
                labels: item.labels,
                contractText: item.contract_text || item.contractText,
                acceptanceText: item.acceptance_text || item.acceptanceText,
                receiptText: item.receipt_text || item.receiptText,
                confirmationText: item.confirmation_text || item.confirmationText,
            },
        ])),
    });
    return normalized;
};

const ticketPdfTemplateToApi = (template = {}) => ({
    default_language: template.language || 'de',
    logo_url: template.logoUrl || '',
    brand_title: template.brandTitle || '',
    brand_subtitle: template.brandSubtitle || '',
    brand_tagline: template.brandTagline || '',
    footer: template.footer || {},
    translations: Object.fromEntries(Object.entries(template.translations || {}).map(([language, item]) => [
        language,
        {
            name: item.name || language.toUpperCase(),
            labels: DEFAULT_TICKET_PDF_TEMPLATE.translations[language]?.labels
                || DEFAULT_TICKET_PDF_TEMPLATE.translations.en.labels,
            contract_text: item.contractText || '',
            acceptance_text: item.acceptanceText || '',
            receipt_text: item.receiptText || '',
            confirmation_text: item.confirmationText || '',
        },
    ])),
});

const fetchTicketPdfTemplate = async (companyId = null) => {
    if (!companyId) return loadTicketPdfTemplate();
    const payload = await apiFetch(`/companies/${companyId}/ticket-pdf-template`);
    return ticketPdfTemplateFromApi(payload.data || {});
};

const persistTicketPdfTemplate = async (companyId, template) => {
    if (!companyId) {
        saveTicketPdfTemplate(template);
        return normalizeTicketPdfTemplate(template);
    }
    const payload = await apiFetch(`/companies/${companyId}/ticket-pdf-template`, {
        method: 'PATCH',
        body: ticketPdfTemplateToApi(template),
    });
    return ticketPdfTemplateFromApi(payload.data || {});
};

const uploadTicketPdfLogo = async (companyId, file) => {
    if (!companyId || !file) return null;
    const uploadFile = await optimizeImageForUpload(file);
    const formData = new FormData();
    formData.append('logo', uploadFile);
    const payload = await apiFetch(`/companies/${companyId}/ticket-pdf-template/logo`, {
        method: 'POST',
        body: formData,
    });
    return payload.logo_url || payload.data?.logo_url || null;
};

const ticketPdfLanguageForAppointment = (appointment = {}, fallbackLanguage = 'de') => {
    const code = String(appointment.customer?.phone_country_code || '').replace(/\s+/g, '');
    const languageByCode = {
        '+90': 'tr',
        '+49': 'de',
        '+44': 'en',
        '+48': 'pl',
        '+31': 'nl',
        '+7': 'ru',
        '+41': 'ch',
        '+32': 'be',
        '+372': 'et',
        '+46': 'sv',
        '+47': 'no',
        '+45': 'da',
        '+358': 'fi',
    };
    return languageByCode[code] || (DEFAULT_TICKET_PDF_TEMPLATE.translations[fallbackLanguage] ? fallbackLanguage : 'en');
};

const ticketPdfLocale = (language) => ({
    de: 'de-DE',
    tr: 'tr-TR',
    en: 'en-GB',
    pl: 'pl-PL',
    nl: 'nl-NL',
    ru: 'ru-RU',
    ch: 'de-CH',
    be: 'nl-BE',
    et: 'et-EE',
    sv: 'sv-SE',
    no: 'nb-NO',
    da: 'da-DK',
    fi: 'fi-FI',
}[language] || 'tr-TR');

const ticketPdfMoney = (value, language = 'tr') =>
    new Intl.NumberFormat(ticketPdfLocale(language), {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
    }).format(Number(value || 0));

const ticketPdfDateParts = (value, language = 'tr') => {
    const date = value ? new Date(value) : new Date();
    return {
        date: new Intl.DateTimeFormat(ticketPdfLocale(language), { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date),
        time: new Intl.DateTimeFormat(ticketPdfLocale(language), { hour: '2-digit', minute: '2-digit' }).format(date),
    };
};

const paragraphHtml = (value) =>
    String(value || '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => `<p>${escapeHtml(line)}</p>`)
        .join('');

const ticketPdfDisplayData = (appointment = {}, language = 'de') => {
    const appointmentDate = ticketPdfDateParts(appointment.appointment_at, language);
    const documentDate = ticketPdfDateParts(new Date().toISOString(), language);
    const customerName = `${appointment.customer?.first_name || ''} ${appointment.customer?.last_name || ''}`.trim();
    const phone = `${appointment.customer?.phone_country_code || ''} ${appointment.customer?.phone_number || ''}`.trim();
    const hotelRoom = [appointment.customer?.hotel_name, appointment.customer?.room_number].filter(Boolean).join(' / ');
    const price = Number(appointment.price || 0);
    const deposit = Number(appointment.deposit_amount || 0);
    return {
        documentDate: documentDate.date,
        ticketCode: appointment.ticket_code || appointment.code || `TD-${String(appointment.id || 'DEMO').padStart(6, '0')}`,
        customerName: customerName || 'Demo Müşteri',
        phone: phone || '—',
        hotelRoom: hotelRoom || appointment.place || '—',
        ticketType: ticketMetaLine(appointment) || 'Tattoo',
        infoStaff: appointment.created_by?.name || appointment.info?.name || appointment.created_by_name || '—',
        reservationDate: appointmentDate.date,
        reservationTime: appointmentDate.time,
        pickup: appointment.pickup_required ? (language === 'de' ? 'Ja' : language === 'en' ? 'Yes' : 'Evet') : (language === 'de' ? 'Nein' : language === 'en' ? 'No' : 'Hayır'),
        quantity: appointment.pax || 1,
        deposit: ticketPdfMoney(deposit, language),
        remaining: ticketPdfMoney(Math.max(price - deposit, 0), language),
        artist: appointment.artist?.name || appointment.assigned_artist?.name || '—',
    };
};

const sampleTicketPdfAppointment = () => ({
    id: 1,
    appointment_type: 'tattoo',
    appointment_at: new Date().toISOString(),
    pax: 1,
    price: 500,
    deposit_amount: 100,
    pickup_required: true,
    ticket_type_labels: ['Dövme', 'Piercing'],
    tattoo_type_label: 'Freehand',
    payment_method_label: 'Nakit',
    customer: {
        first_name: 'Demo',
        last_name: 'Müşteri',
        phone_country_code: '+90',
        phone_number: '5551112233',
        hotel_name: 'Demo Hotel',
        room_number: '204',
    },
    artist: { name: 'Artist Demo' },
    created_by: { name: 'Info Demo' },
});

const openTicketPdfPrintWindow = async (appointment = null, companyId = null) => {
    const template = await fetchTicketPdfTemplate(companyId);
    const language = ticketPdfLanguageForAppointment(appointment || {}, template.language || 'de');
    const footer = {
        ...(template.footer || {}),
        instagram: appointment?.studio?.instagram || template.footer?.instagram || '',
        facebook: appointment?.studio?.facebook || template.footer?.facebook || '',
    };
    const instagramIcon = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="2.8" y="2.8" width="18.4" height="18.4" rx="5.2" fill="none" stroke="white" stroke-width="2.2"/>
            <circle cx="12" cy="12" r="4.15" fill="none" stroke="white" stroke-width="2.2"/>
            <circle cx="17.35" cy="6.65" r="1.45" fill="white"/>
        </svg>
    `;
    const facebookIcon = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path fill="white" d="M15.1 8.2h2.35V4.35c-.4-.06-1.78-.17-3.38-.17-3.35 0-5.65 2.1-5.65 5.95v3.35H4.65v4.3h3.77V24h4.62v-6.22h3.62l.57-4.3h-4.19V10.55c0-1.24.34-2.35 2.06-2.35Z"/>
        </svg>
    `;
    const translation = template.translations[language] || template.translations.de;
    const labels = translation.labels || {};
    const data = ticketPdfDisplayData(appointment || sampleTicketPdfAppointment(), language);
    const labelRowsLeft = ['documentDate', 'ticketCode', 'customerName', 'phone', 'hotelRoom', 'ticketType', 'infoStaff'];
    const labelRowsRight = ['reservationDate', 'reservationTime', 'pickup', 'quantity', 'deposit', 'remaining', 'artist'];
    const rowHtml = (key) => `
        <div class="ticket-info-row">
            <span>${escapeHtml(labels[key] || key)} :</span>
            <strong>${escapeHtml(data[key] || '—')}</strong>
        </div>
    `;
    const logoHtml = template.logoUrl
        ? `<img src="${escapeHtml(template.logoUrl)}" alt="Logo">`
        : `<div class="print-logo-fallback">${escapeHtml(template.brandTitle || 'Tattoodesk')}</div>`;
    const watermarkHtml = template.logoUrl
        ? `<img src="${escapeHtml(template.logoUrl)}" alt="Watermark logo">`
        : '';
    const qrCodeUrl = appointment?.public_history_url
        ? `https://api.qrserver.com/v1/create-qr-code/?size=220x220&ecc=H&margin=10&data=${encodeURIComponent(appointment.public_history_url)}`
        : '';
    const qrHtml = qrCodeUrl
        ? `
            <img class="qr-code-image" src="${qrCodeUrl}" alt="QR">
            ${template.logoUrl ? `
                <span class="qr-logo">
                    <img src="${escapeHtml(template.logoUrl)}" alt="QR logo">
                </span>
            ` : ''}
        `
        : escapeHtml(data.ticketCode);
    const printWindow = window.open('', '_blank', 'width=900,height=1100');
    if (!printWindow) {
        showToast('Yazdırma penceresi açılamadı. Tarayıcı popup iznini kontrol edin.', 'error');
        return;
    }
    printWindow.document.write(`
        <!doctype html>
        <html lang="${escapeHtml(language)}">
        <head>
            <meta charset="utf-8">
            <title>${escapeHtml(data.ticketCode)} PDF</title>
            <style>
                @page { size: A4; margin: 18mm; }
                * { box-sizing: border-box; }
                body { margin: 0; background: #eceff3; color: #1b1f28; font-family: Arial, Helvetica, sans-serif; }
                .sheet { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 18mm; background: #fff; position: relative; overflow: hidden; }
                .watermark { position: absolute; left: 50%; top: 52%; transform: translate(-50%, -50%); width: 620px; height: 620px; opacity: .18; pointer-events: none; display: grid; place-items: center; }
                .watermark img { max-width: 100%; max-height: 100%; object-fit: contain; }
                .ticket-header { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; }
                .brand { display: flex; gap: 14px; align-items: center; color: #b79a50; }
                .brand-logo { width: 84px; height: 84px; display: grid; place-items: center; border: 1px solid rgba(183,154,80,.35); border-radius: 12px; overflow: hidden; }
                .brand-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
                .print-logo-fallback { font-size: 11px; font-weight: 800; text-align: center; padding: 8px; }
                .brand-title { font-size: 31px; font-weight: 800; line-height: 1; letter-spacing: .04em; }
                .brand-subtitle { margin-top: 5px; font-size: 13px; font-weight: 700; letter-spacing: .22em; color: #1b1f28; }
                .brand-tagline { margin-top: 7px; font-size: 8px; letter-spacing: .16em; color: #b79a50; }
                .qr { position: relative; width: 86px; height: 86px; border: 2px solid #20242c; display: grid; place-items: center; font-size: 11px; font-weight: 800; text-align: center; background:
                    linear-gradient(90deg, #20242c 8px, transparent 8px) 0 0 / 18px 18px,
                    linear-gradient(#20242c 8px, transparent 8px) 0 0 / 18px 18px,
                    #fff; color: #20242c; overflow: hidden; }
                .qr-code-image { width: 100%; height: 100%; object-fit: cover; background: #fff; display: block; }
                .qr-logo { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 27px; height: 27px; border-radius: 8px; background: #fff; border: 2px solid #fff; box-shadow: 0 2px 7px rgba(17,24,39,.22); padding: 3px; display: grid; place-items: center; }
                .qr-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
                .ticket-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 34px; margin-top: 34px; }
                .ticket-info-row { display: grid; grid-template-columns: 160px 1fr; gap: 8px; align-items: baseline; font-size: 12px; margin-bottom: 12px; }
                .ticket-info-row span { font-weight: 700; color: #111827; }
                .ticket-info-row strong { min-height: 18px; border-bottom: 1px solid #d8dbe2; font-size: 12px; font-weight: 600; color: #20242c; }
                .contract { margin-top: 34px; font-size: 12px; line-height: 1.75; color: #222733; }
                .contract p { margin: 0 0 11px; }
                .acceptance { margin-top: 24px; font-size: 12px; line-height: 1.65; }
                .signature { margin-top: 34px; display: grid; grid-template-columns: 1fr 190px; gap: 30px; align-items: end; }
                .signature-line { border-bottom: 1px solid #111827; height: 54px; }
                .signature-title { margin-top: 8px; text-align: center; font-size: 12px; font-weight: 800; letter-spacing: .08em; }
                .footer { position: absolute; left: 18mm; right: 18mm; bottom: 16mm; display: flex; justify-content: space-between; gap: 18px; font-size: 11px; color: #222733; }
                .footer strong { color: #b79a50; }
                .social-row { display: flex; align-items: center; gap: 7px; justify-content: flex-end; margin-bottom: 5px; }
                .social-icon { width: 17px; height: 17px; border-radius: 5px; display: inline-grid; place-items: center; color: #fff; overflow: hidden; }
                .social-icon svg { width: 13px; height: 13px; display: block; }
                .social-icon--instagram { background: linear-gradient(135deg, #f58529, #dd2a7b 45%, #8134af 75%, #515bd4); }
                .social-icon--facebook { background: #1877f2; border-radius: 50%; }
                @media print {
                    body { background: #fff; }
                    .sheet { margin: 0; box-shadow: none; }
                }
            </style>
        </head>
        <body>
            <main class="sheet">
                ${watermarkHtml ? `<div class="watermark">${watermarkHtml}</div>` : ''}
                <header class="ticket-header">
                    <div class="brand">
                        <div class="brand-logo">${logoHtml}</div>
                        <div>
                            <div class="brand-title">${escapeHtml(template.brandTitle)}</div>
                            <div class="brand-subtitle">${escapeHtml(template.brandSubtitle)}</div>
                            <div class="brand-tagline">${escapeHtml(template.brandTagline)}</div>
                        </div>
                    </div>
                    <div class="qr">
                        ${qrHtml}
                    </div>
                </header>
                <section class="ticket-grid">
                    <div>${labelRowsLeft.map(rowHtml).join('')}</div>
                    <div>${labelRowsRight.map(rowHtml).join('')}</div>
                </section>
                <section class="contract">${paragraphHtml(translation.contractText)}</section>
                <section class="acceptance">
                    <p>${escapeHtml(translation.acceptanceText || '')}</p>
                    <p>${escapeHtml(translation.receiptText || '')}</p>
                    <p>${escapeHtml(translation.confirmationText || '')}</p>
                </section>
                <section class="signature">
                    <div>${escapeHtml(data.documentDate)} ${escapeHtml(data.reservationTime)}</div>
                    <div>
                        <div class="signature-line"></div>
                        <div class="signature-title">${escapeHtml(labels.signature || 'SIGNATURE')}</div>
                    </div>
                </section>
                <footer class="footer">
                    <div>
                        <div><strong>Email</strong> ${escapeHtml(footer.email)}</div>
                        <div><strong>Tel</strong> ${escapeHtml(footer.phone)}</div>
                        <div>${escapeHtml(footer.address)}</div>
                    </div>
                    <div>
                        ${footer.instagram ? `<div class="social-row"><span class="social-icon social-icon--instagram">${instagramIcon}</span><span>${escapeHtml(footer.instagram)}</span></div>` : ''}
                        ${footer.facebook ? `<div class="social-row"><span class="social-icon social-icon--facebook">${facebookIcon}</span><span>${escapeHtml(footer.facebook)}</span></div>` : ''}
                    </div>
                </footer>
            </main>
            <script>
                window.addEventListener('load', () => {
                    window.setTimeout(() => window.print(), 250);
                });
            </script>
        </body>
        </html>
    `);
    printWindow.document.close();
};

const ticketPdfTemplateEditorHtml = (template, activeLanguage) => {
    const translation = template.translations[activeLanguage] || template.translations.de;
    const logoPreview = template.logoUrl
        ? `<img src="${escapeHtml(template.logoUrl)}" alt="PDF logo" style="width:100%;height:100%;object-fit:contain">`
        : '<span style="font-size:0.72rem;color:var(--text-subtle);font-weight:800">Logo</span>';
    return `
        <div style="display:grid;gap:1rem">
            <section style="border:1px solid var(--border);background:var(--surface-soft);border-radius:1rem;padding:1rem">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.85rem">
                    <div>
                        <div class="section-eyebrow" style="margin-bottom:0.18rem">Dil</div>
                        <div style="font-size:0.92rem;font-weight:850;color:var(--text-main)">PDF metin dilini seç</div>
                    </div>
                    <span class="badge-pill">${escapeHtml(translation.name || activeLanguage.toUpperCase())}</span>
                </div>
                <div style="display:flex;gap:0.45rem;flex-wrap:wrap">
                    ${Object.entries(template.translations).map(([language, item]) => `
                        <button class="${language === activeLanguage ? 'button-primary' : 'button-secondary'}" data-ticket-pdf-language="${language}" style="padding:0.45rem 0.72rem;font-size:0.72rem">${escapeHtml(item.name || language.toUpperCase())}</button>
                    `).join('')}
                </div>
            </section>
            <section style="border:1px solid var(--border);background:var(--surface);border-radius:1rem;padding:1rem">
                <div style="display:grid;grid-template-columns:120px 1fr;gap:1rem;align-items:start">
                    <div style="width:120px;aspect-ratio:1;border:1px solid var(--border);background:var(--surface-soft);border-radius:1rem;display:grid;place-items:center;overflow:hidden">
                        ${logoPreview}
                    </div>
                    <div class="form-grid">
                        <div>
                            <div class="section-eyebrow" style="margin-bottom:0.18rem">Marka</div>
                            <div style="font-size:0.92rem;font-weight:850;color:var(--text-main)">Logo ve başlık bilgileri</div>
                        </div>
                        <div class="form-grid form-grid--split">
                            <div class="field-wrap">
                                <label class="field-label">Logo URL / Base64</label>
                                <input class="field-input" data-ticket-pdf-field="logoUrl" value="${escapeHtml(template.logoUrl || '')}" placeholder="https://... veya dosya seç">
                            </div>
                            <div class="field-wrap">
                                <label class="field-label">Logo Dosyası</label>
                                <input class="field-input" type="file" accept="image/*" data-ticket-pdf-logo-file>
                            </div>
                        </div>
                        <div class="form-grid form-grid--split">
                            <div class="field-wrap"><label class="field-label">Marka Başlığı</label><input class="field-input" data-ticket-pdf-field="brandTitle" value="${escapeHtml(template.brandTitle)}"></div>
                            <div class="field-wrap"><label class="field-label">Alt Başlık</label><input class="field-input" data-ticket-pdf-field="brandSubtitle" value="${escapeHtml(template.brandSubtitle)}"></div>
                        </div>
                        <div class="field-wrap"><label class="field-label">Slogan</label><input class="field-input" data-ticket-pdf-field="brandTagline" value="${escapeHtml(template.brandTagline)}"></div>
                    </div>
                </div>
            </section>
            <section style="border:1px solid var(--border);background:var(--surface);border-radius:1rem;padding:1rem">
                <div style="margin-bottom:0.85rem">
                    <div class="section-eyebrow" style="margin-bottom:0.18rem">İletişim</div>
                    <div style="font-size:0.92rem;font-weight:850;color:var(--text-main)">PDF alt alanı ve sosyal bilgiler</div>
                </div>
                <div class="form-grid form-grid--split">
                    <div class="field-wrap"><label class="field-label">E-posta</label><input class="field-input" data-ticket-pdf-footer="email" value="${escapeHtml(template.footer.email)}"></div>
                    <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" data-ticket-pdf-footer="phone" value="${escapeHtml(template.footer.phone)}"></div>
                </div>
                <div class="field-wrap" style="margin-top:0.8rem"><label class="field-label">Adres</label><input class="field-input" data-ticket-pdf-footer="address" value="${escapeHtml(template.footer.address)}"></div>
                <div class="form-grid form-grid--split" style="margin-top:0.8rem">
                    <div class="field-wrap"><label class="field-label">Instagram</label><input class="field-input" data-ticket-pdf-footer="instagram" value="${escapeHtml(template.footer.instagram)}"></div>
                    <div class="field-wrap"><label class="field-label">Facebook</label><input class="field-input" data-ticket-pdf-footer="facebook" value="${escapeHtml(template.footer.facebook)}"></div>
                </div>
            </section>
            <section style="border:1px solid var(--border);background:var(--surface);border-radius:1rem;padding:1rem">
                <div style="margin-bottom:0.85rem">
                    <div class="section-eyebrow" style="margin-bottom:0.18rem">Metinler</div>
                    <div style="font-size:0.92rem;font-weight:850;color:var(--text-main)">Seçili dile ait sözleşme ve onay metinleri</div>
                </div>
                <div style="border:1px solid var(--border);background:var(--surface-soft);border-radius:0.9rem;padding:0.85rem 1rem;color:var(--text-muted);font-size:0.78rem;line-height:1.55;margin-bottom:0.85rem">
                    PDF alan başlıkları otomatik çevrilir. Müşteri verisi yalnızca karşısındaki değer kısmını doldurur.
                </div>
                <div class="field-wrap">
                    <label class="field-label">Sözleşme Metni (${escapeHtml(translation.name || activeLanguage.toUpperCase())})</label>
                    <textarea class="field-input" rows="8" data-ticket-pdf-translation="contractText">${escapeHtml(translation.contractText || '')}</textarea>
                </div>
                <div class="form-grid form-grid--split" style="margin-top:0.8rem">
                    <div class="field-wrap"><label class="field-label">Kabul Metni</label><textarea class="field-input" rows="3" data-ticket-pdf-translation="acceptanceText">${escapeHtml(translation.acceptanceText || '')}</textarea></div>
                    <div class="field-wrap"><label class="field-label">Makbuz Metni</label><textarea class="field-input" rows="3" data-ticket-pdf-translation="receiptText">${escapeHtml(translation.receiptText || '')}</textarea></div>
                </div>
                <div class="field-wrap" style="margin-top:0.8rem"><label class="field-label">Onay Metni</label><textarea class="field-input" rows="3" data-ticket-pdf-translation="confirmationText">${escapeHtml(translation.confirmationText || '')}</textarea></div>
            </section>
        </div>
    `;
};

const openTicketPdfTemplateEditor = async (companyId = null, previewAppointment = null) => {
    let template = await fetchTicketPdfTemplate(companyId);
    let activeLanguage = template.language || 'de';
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.64);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;padding:1.25rem';
    overlay.innerHTML = `
        <div style="width:min(96vw,1080px);max-height:92vh;overflow:auto;border:1px solid var(--border);background:var(--surface);border-radius:1.25rem;box-shadow:0 24px 70px rgba(2,6,23,.32)">
            <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;padding:1.25rem 1.35rem;background:linear-gradient(135deg, color-mix(in srgb, var(--primary) 16%, var(--surface)), var(--surface));border-bottom:1px solid var(--border)">
                <div style="display:flex;align-items:center;gap:0.85rem">
                    <div style="width:46px;height:46px;border-radius:1rem;background:var(--surface);border:1px solid var(--border);display:grid;place-items:center;color:var(--primary);font-weight:900">PDF</div>
                    <div>
                        <div class="section-eyebrow">PDF Şablonu</div>
                        <div class="section-title">Bilet Yazdırma Alanı</div>
                        <p style="margin-top:0.35rem;font-size:0.78rem;color:var(--text-muted)">Logo, iletişim ve dil bazlı metinleri düzenleyin.</p>
                    </div>
                </div>
                <button class="button-secondary" data-ticket-pdf-close style="padding:0.45rem 0.7rem">Kapat</button>
            </div>
            <div data-ticket-pdf-editor-body style="padding:1.35rem"></div>
            <div style="position:sticky;bottom:0;padding:1rem 1.35rem;background:color-mix(in srgb, var(--surface) 92%, transparent);backdrop-filter:blur(14px);border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:0.6rem;flex-wrap:wrap">
                ${companyId ? '' : '<button class="button-secondary" data-ticket-pdf-reset>Varsayılana Dön</button>'}
                <button class="button-secondary" data-ticket-pdf-preview>Önizle / Yazdır</button>
                <button class="button-primary" data-ticket-pdf-save>Kaydet</button>
            </div>
        </div>
    `;
    const body = qs('[data-ticket-pdf-editor-body]', overlay);
    const readForm = () => {
        overlay.querySelectorAll('[data-ticket-pdf-field]').forEach((input) => {
            template[input.getAttribute('data-ticket-pdf-field')] = input.value;
        });
        overlay.querySelectorAll('[data-ticket-pdf-footer]').forEach((input) => {
            template.footer[input.getAttribute('data-ticket-pdf-footer')] = input.value;
        });
        overlay.querySelectorAll('[data-ticket-pdf-label]').forEach((input) => {
            template.translations[activeLanguage].labels[input.getAttribute('data-ticket-pdf-label')] = input.value;
        });
        overlay.querySelectorAll('[data-ticket-pdf-translation]').forEach((input) => {
            template.translations[activeLanguage][input.getAttribute('data-ticket-pdf-translation')] = input.value;
        });
        template.language = activeLanguage;
    };
    const render = () => {
        body.innerHTML = ticketPdfTemplateEditorHtml(template, activeLanguage);
        body.querySelectorAll('[data-ticket-pdf-language]').forEach((button) => {
            button.addEventListener('click', () => {
                readForm();
                activeLanguage = button.getAttribute('data-ticket-pdf-language') || 'de';
                template.language = activeLanguage;
                render();
            });
        });
        qs('[data-ticket-pdf-logo-file]', body)?.addEventListener('change', (event) => {
            const file = event.target.files?.[0];
            if (!file) return;
            if (companyId) {
                handleAsync(async () => {
                    readForm();
                    const logoUrl = await uploadTicketPdfLogo(companyId, file);
                    if (logoUrl) {
                        template.logoUrl = logoUrl;
                        const input = qs('[data-ticket-pdf-field="logoUrl"]', body);
                        if (input) input.value = logoUrl;
                        showToast('PDF logosu yüklendi.', 'success');
                    }
                });
                return;
            }
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                template.logoUrl = String(reader.result || '');
                const input = qs('[data-ticket-pdf-field="logoUrl"]', body);
                if (input) input.value = template.logoUrl;
            });
            reader.readAsDataURL(file);
        });
    };
    const close = () => overlay.remove();
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) close();
    });
    qs('[data-ticket-pdf-close]', overlay)?.addEventListener('click', close);
    qs('[data-ticket-pdf-reset]', overlay)?.addEventListener('click', () => {
        if (!window.confirm('PDF şablonu varsayılan demo haline dönsün mü?')) return;
        template = cloneTicketPdfTemplate();
        activeLanguage = template.language;
        saveTicketPdfTemplate(template);
        render();
        showToast('PDF şablonu varsayılana döndü.', 'success');
    });
    qs('[data-ticket-pdf-save]', overlay)?.addEventListener('click', () => {
        handleAsync(async () => {
            readForm();
            template = await persistTicketPdfTemplate(companyId, template);
            activeLanguage = template.language || activeLanguage;
            showToast('PDF şablonu kaydedildi.', 'success');
        });
    });
    qs('[data-ticket-pdf-preview]', overlay)?.addEventListener('click', () => {
        handleAsync(async () => {
            readForm();
            await persistTicketPdfTemplate(companyId, template);
            await openTicketPdfPrintWindow(previewAppointment || sampleTicketPdfAppointment(), companyId);
        });
    });
    render();
    document.body.appendChild(overlay);
};

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
    const pathIsTickets = window.location.pathname.includes('/admin/tickets');
    const routeRecordType = pathIsTickets ? 'tattoo' : 'designer';
    const recordTypeLocked = window.location.pathname.includes('/admin/tickets')
        || window.location.pathname.includes('/admin/appointments');
    let activeRecordType = routeRecordType;
    let activeTimeScope = 'upcoming';
    let appointmentStatusFilter = '';
    let appointmentDateFrom = '';
    let appointmentDateTo = '';
    let appointmentSearchQuery = '';
    const title = isDriverRole()
        ? 'Transferler'
        : isArtistLikeRole()
            ? 'Atanan Biletler'
            : pathIsTickets
                ? 'Bilet Yönetimi'
                : 'Randevu Yönetimi';
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
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;justify-content:flex-end;align-items:flex-end">
                        <div class="field-wrap" style="min-width:min(100%,260px)">
                            <label class="field-label">Genel Arama</label>
                            <input class="field-input" data-appointments-search placeholder="Ad, telefon, otel, oda, not..." value="${escapeHtml(appointmentSearchQuery)}" style="padding:0.55rem 0.75rem;font-size:0.78rem">
                        </div>
                        ${canCreateAppointmentWeb() ? `<button class="button-primary" data-open-appointment-create style="padding:0.55rem 0.85rem;font-size:0.78rem;flex-shrink:0">${pathIsTickets ? 'Bilet Aç' : 'Randevu Oluştur'}</button>` : ''}
                        <button class="button-secondary" data-appointments-filter style="padding:0.55rem 0.85rem;font-size:0.78rem;flex-shrink:0">Filtrele</button>
                        <button class="button-secondary" data-appointments-refresh style="padding:0.55rem 0.85rem;font-size:0.78rem;flex-shrink:0">Yenile</button>
                    </div>
                </div>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.9rem" data-appointments-tabs>
                    <span class="badge-pill ${activeRecordType === 'tattoo' ? 'badge-pill--purple' : 'badge-pill--teal'}" style="font-size:0.72rem">${activeRecordType === 'tattoo' ? 'Sadece Biletler' : 'Sadece Randevular'}</span>
                    <button class="${activeTimeScope === 'upcoming' ? 'button-primary' : 'button-secondary'}" data-appointments-time-tab="upcoming" style="padding:0.5rem 0.85rem;font-size:0.78rem">Gelecek</button>
                    <button class="${activeTimeScope === 'past' ? 'button-primary' : 'button-secondary'}" data-appointments-time-tab="past" style="padding:0.5rem 0.85rem;font-size:0.78rem">Geçmiş</button>
                    <button class="${activeTimeScope === 'all' ? 'button-primary' : 'button-secondary'}" data-appointments-time-tab="all" style="padding:0.5rem 0.85rem;font-size:0.78rem">Tümü</button>
                </div>
                <div class="list-stack" data-appointments-list>${skeletonGrid(4)}</div>
            </div>
        </div>
    `;

    const studioSelect = qs('[data-appointments-studio-select]', root);
    const lockedStudioLabel = qs('[data-appointments-locked-studio]', root);
    const listNode = qs('[data-appointments-list]', root);
    let studioOptions = [];
    let lastRenderedAppointments = [];

    const companyIdForAppointment = (appointment = null) => {
        const appointmentStudioId = appointment?.studio?.id || studioSelect?.value;
        const option = studioOptions.find((studio) => String(studio.id) === String(appointmentStudioId));
        return appointment?.studio?.company_id
            || appointment?.studio?.company?.id
            || option?.company_id
            || option?.company?.id
            || null;
    };

    const syncAppointmentTabs = () => {
        root.querySelectorAll('[data-appointments-type-tab]').forEach((button) => {
            const active = button.getAttribute('data-appointments-type-tab') === activeRecordType;
            button.classList.toggle('button-primary', active);
            button.classList.toggle('button-secondary', !active);
        });
        root.querySelectorAll('[data-appointments-time-tab]').forEach((button) => {
            const active = button.getAttribute('data-appointments-time-tab') === activeTimeScope;
            button.classList.toggle('button-primary', active);
            button.classList.toggle('button-secondary', !active);
        });
    };

    const openAppointmentFilterPopup = () => {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.48);display:flex;align-items:center;justify-content:center;padding:1rem';
        overlay.innerHTML = `
            <div class="panel-card" style="width:min(100%,540px);max-height:90vh;overflow:auto;padding:1.2rem">
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:1rem">
                    <div>
                        <div class="section-eyebrow">Kayıt Filtresi</div>
                        <div class="section-title">Randevu / Bilet Listele</div>
                    </div>
                    <button class="button-secondary" data-close-appointments-filter style="padding:0.45rem 0.7rem">Kapat</button>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:0.8rem">
                    ${recordTypeLocked ? '' : `
                        <div class="field-wrap">
                            <label class="field-label">Kayıt Türü</label>
                            <select class="field-select" data-popup-appointment-type>
                                <option value="designer" ${activeRecordType === 'designer' ? 'selected' : ''}>Randevu</option>
                                <option value="tattoo" ${activeRecordType === 'tattoo' ? 'selected' : ''}>Bilet</option>
                            </select>
                        </div>
                    `}
                    <div class="field-wrap">
                        <label class="field-label">Zaman</label>
                        <select class="field-select" data-popup-appointment-time>
                            <option value="upcoming" ${activeTimeScope === 'upcoming' ? 'selected' : ''}>Gelecek</option>
                            <option value="past" ${activeTimeScope === 'past' ? 'selected' : ''}>Geçmiş</option>
                            <option value="all" ${activeTimeScope === 'all' ? 'selected' : ''}>Tümü</option>
                        </select>
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Durum</label>
                        <select class="field-select" data-popup-appointment-status>
                            <option value="" ${appointmentStatusFilter === '' ? 'selected' : ''}>Tümü</option>
                            ${['confirmed','in_progress','completed','cancelled','rescheduled'].map((status) =>
                                `<option value="${status}" ${appointmentStatusFilter === status ? 'selected' : ''}>${statusLabel(status)}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Başlangıç</label>
                        <input class="field-input" type="date" value="${escapeHtml(appointmentDateFrom)}" data-popup-appointment-date-from>
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Bitiş</label>
                        <input class="field-input" type="date" value="${escapeHtml(appointmentDateTo)}" data-popup-appointment-date-to>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:0.6rem;flex-wrap:wrap;margin-top:1rem">
                    <button class="button-secondary" data-clear-appointments-filter>Temizle</button>
                    <button class="button-primary" data-apply-appointments-filter>Listele</button>
                </div>
            </div>
        `;
        const close = () => overlay.remove();
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) close();
        });
        qs('[data-close-appointments-filter]', overlay)?.addEventListener('click', close);
        qs('[data-clear-appointments-filter]', overlay)?.addEventListener('click', () => {
            qs('[data-popup-appointment-status]', overlay).value = '';
            qs('[data-popup-appointment-date-from]', overlay).value = '';
            qs('[data-popup-appointment-date-to]', overlay).value = '';
        });
        qs('[data-apply-appointments-filter]', overlay)?.addEventListener('click', () => {
            activeRecordType = recordTypeLocked
                ? routeRecordType
                : (qs('[data-popup-appointment-type]', overlay)?.value || 'designer');
            activeTimeScope = qs('[data-popup-appointment-time]', overlay)?.value || 'upcoming';
            appointmentStatusFilter = qs('[data-popup-appointment-status]', overlay)?.value || '';
            appointmentDateFrom = qs('[data-popup-appointment-date-from]', overlay)?.value || '';
            appointmentDateTo = qs('[data-popup-appointment-date-to]', overlay)?.value || '';
            if (appointmentDateFrom && appointmentDateTo && appointmentDateFrom > appointmentDateTo) {
                const nextFrom = appointmentDateTo;
                appointmentDateTo = appointmentDateFrom;
                appointmentDateFrom = nextFrom;
            }
            close();
            syncAppointmentTabs();
            handleAsync(renderAppointments);
        });
        document.body.appendChild(overlay);
    };

    const loadStudios = async () => {
        if (!studioSelect && !canCreateAppointmentWeb()) return;
        const payload = await apiFetch('/studios/options');
        const studios = uniqueById(payload.data || []);
        studioOptions = studios;
        if (studioSelect) {
            studioSelect.innerHTML = studios.map((s) => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        }
        if (locksToOwnStudio && studios[0]?.id) {
            if (studioSelect) studioSelect.value = String(studios[0].id);
        }
        if (lockedStudioLabel) {
            lockedStudioLabel.textContent = studios[0]?.name
                ? `Stüdyo: ${studios[0].name}`
                : 'Atanmış stüdyo bulunamadı';
        }
    };

    const appointmentCreateFormMarkup = () => `
        <form data-appointment-create-form enctype="multipart/form-data" style="display:grid;gap:1rem">
            <section style="border:1px solid var(--border);background:var(--surface-soft);border-radius:1rem;padding:1rem">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;margin-bottom:0.85rem">
                    <div>
                        <div class="section-eyebrow" style="margin-bottom:0.18rem">Kapsam</div>
                        <div style="font-size:0.92rem;font-weight:850;color:var(--text-main)">Kayıt hangi stüdyoya açılıyor?</div>
                    </div>
                    <div class="badge-pill ${routeRecordType === 'tattoo' ? 'badge-pill--purple' : 'badge-pill--teal'}" style="flex-shrink:0">${routeRecordType === 'tattoo' ? 'Bilet' : 'Randevu'}</div>
                </div>
                <div class="form-grid form-grid--split">
                    <div class="field-wrap">
                        <label class="field-label">Stüdyo</label>
                        <select class="field-select" name="studio_id" data-appointment-create-studio ${locksToOwnStudio ? 'style="display:none"' : ''} required></select>
                        ${locksToOwnStudio ? '<div class="badge-pill" data-appointment-create-locked-studio>Stüdyo yükleniyor...</div>' : ''}
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Tür</label>
                        <input type="hidden" name="appointment_type" value="${routeRecordType}">
                        <div style="min-height:44px;border:1px solid var(--border);background:var(--surface);border-radius:0.8rem;padding:0.68rem 0.8rem;color:var(--text-main);font-size:0.82rem;font-weight:800">${routeRecordType === 'tattoo' ? 'Dövme / piercing bileti' : 'Tasarım randevusu'}</div>
                    </div>
                </div>
            </section>
            ${routeRecordType === 'tattoo' ? `
            <section style="border:1px solid var(--border);background:var(--surface);border-radius:1rem;padding:1rem">
                <div style="margin-bottom:0.85rem">
                    <div class="section-eyebrow" style="margin-bottom:0.18rem">Bilet Bilgisi</div>
                    <div style="font-size:0.92rem;font-weight:850;color:var(--text-main)">İşlem türü ve ödeme detayları</div>
                </div>
                ${ticketFieldsMarkup()}
                <div class="field-wrap" style="margin-top:0.85rem">
                    <label class="field-label">Infocu Hakedişi</label>
                    <select class="field-select" name="assigned_info_user_id" data-ticket-info-select>
                        <option value="">Infocu seçilmedi</option>
                    </select>
                    <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-subtle)">Seçilen infocu, bilet tamamlanınca kendi komisyon oranı kadar hakediş kazanır.</div>
                </div>
            </section>
            ` : ticketFieldsMarkup()}
            <section style="border:1px solid var(--border);background:var(--surface);border-radius:1rem;padding:1rem">
                <div style="margin-bottom:0.85rem">
                    <div class="section-eyebrow" style="margin-bottom:0.18rem">Müşteri</div>
                    <div style="font-size:0.92rem;font-weight:850;color:var(--text-main)">Telefonla eski kayıt kontrolü</div>
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
                    <div class="field-wrap"><label class="field-label">Telefon</label><input class="field-input" name="customer[phone_number]" inputmode="tel" placeholder="5557778899"></div>
                </div>
                <div data-old-customer-panel style="display:none;margin-top:0.85rem"></div>
                <div class="form-grid form-grid--split" style="margin-top:0.85rem">
                    <div class="field-wrap"><label class="field-label">Ad</label><input class="field-input" name="customer[first_name]" required></div>
                    <div class="field-wrap"><label class="field-label">Soyad</label><input class="field-input" name="customer[last_name]" required></div>
                </div>
                <div class="form-grid form-grid--split" style="margin-top:0.8rem">
                    <div class="field-wrap"><label class="field-label">Otel</label><input class="field-input" name="customer[hotel_name]"></div>
                    <div class="field-wrap"><label class="field-label">Oda</label><input class="field-input" name="customer[room_number]"></div>
                </div>
            </section>
            <section style="border:1px solid var(--border);background:var(--surface);border-radius:1rem;padding:1rem">
                <div style="margin-bottom:0.85rem">
                    <div class="section-eyebrow" style="margin-bottom:0.18rem">Zaman ve Tutar</div>
                    <div style="font-size:0.92rem;font-weight:850;color:var(--text-main)">Planlama bilgileri</div>
                </div>
                <div class="form-grid form-grid--split">
                    <div class="field-wrap"><label class="field-label">Tarih/Saat</label><input class="field-input" name="appointment_at" type="datetime-local" required></div>
                    <div class="field-wrap"><label class="field-label">Kişi</label><input class="field-input" name="pax" type="number" min="1" value="1" required></div>
                </div>
                <div class="form-grid form-grid--split" style="margin-top:0.8rem">
                    <div class="field-wrap" data-price-field><label class="field-label">Fiyat <span style="color:var(--text-subtle)">(opsiyonel)</span></label><input class="field-input" name="price" type="number" min="0" step="0.01"></div>
                    <div class="field-wrap" data-deposit-field><label class="field-label">Depozito <span style="color:var(--text-subtle)">(opsiyonel)</span></label><input class="field-input" name="deposit_amount" type="number" min="0" step="0.01" data-ticket-input></div>
                </div>
                <label data-pickup-field style="margin-top:0.85rem;display:flex;align-items:center;gap:0.5rem;width:max-content;max-width:100%;border:1px solid var(--border);background:var(--surface-soft);border-radius:999px;padding:0.48rem 0.72rem;font-size:0.78rem;color:var(--text-muted)"><input type="checkbox" name="pickup_required" value="1"> Pick up gerekli</label>
            </section>
            <section style="border:1px solid var(--border);background:var(--surface);border-radius:1rem;padding:1rem">
                <div style="margin-bottom:0.85rem">
                    <div class="section-eyebrow" style="margin-bottom:0.18rem">Görseller ve Not</div>
                    <div style="font-size:0.92rem;font-weight:850;color:var(--text-main)">Müşteri fotoğrafı, işlem görselleri ve açıklama</div>
                </div>
                <div class="form-grid form-grid--split">
                    <div class="field-wrap"><label class="field-label">Müşteri Fotoğrafı</label><input class="field-input" name="image" type="file" accept="image/*"></div>
                    <div class="field-wrap"><label class="field-label" data-appointment-images-label>Dövme Görselleri <span style="color:var(--text-subtle)">(en fazla 3)</span></label><input class="field-input" name="tattoo_images[]" type="file" accept="image/*" multiple></div>
                </div>
                <div class="field-wrap" style="margin-top:0.8rem"><label class="field-label">Not</label><textarea class="field-input" name="notes" rows="3"></textarea></div>
            </section>
            <div style="position:sticky;bottom:-1.35rem;margin:0 -1.35rem -1.35rem;padding:1rem 1.35rem;background:color-mix(in srgb, var(--surface) 92%, transparent);backdrop-filter:blur(14px);border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:0.65rem">
                <button class="button-primary" type="submit" style="justify-content:center;min-width:190px">${routeRecordType === 'tattoo' ? 'Bileti Aç' : 'Randevuyu Oluştur'}</button>
            </div>
        </form>
    `;

    const populateCreateStudios = (overlay) => {
        const createStudioSelect = qs('[data-appointment-create-studio]', overlay);
        const createLockedStudioLabel = qs('[data-appointment-create-locked-studio]', overlay);
        if (createStudioSelect) {
            createStudioSelect.innerHTML = studioOptions.map((s) => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
            if (studioSelect?.value) createStudioSelect.value = studioSelect.value;
            if (locksToOwnStudio && studioOptions[0]?.id) createStudioSelect.value = String(studioOptions[0].id);
        }
        if (createLockedStudioLabel) {
            createLockedStudioLabel.textContent = studioOptions[0]?.name
                ? `Stüdyo: ${studioOptions[0].name}`
                : 'Atanmış stüdyo bulunamadı';
        }
    };

    const companyIdForStudio = (studioId = null) => {
        const option = studioOptions.find((studio) => String(studio.id) === String(studioId || studioSelect?.value || ''));
        return option?.company_id || option?.company?.id || null;
    };

    const bindCreatePopupLogo = (overlay) => {
        const logoSlot = qs('[data-appointment-create-logo]', overlay);
        const createStudioSelect = qs('[data-appointment-create-studio]', overlay);
        if (!logoSlot) return;

        const renderFallback = () => {
            logoSlot.innerHTML = `<span>${routeRecordType === 'tattoo' ? 'B' : 'R'}</span>`;
        };

        const loadLogo = async () => {
            const companyId = companyIdForStudio(createStudioSelect?.value);
            if (!companyId) {
                renderFallback();
                return;
            }

            try {
                const template = await fetchTicketPdfTemplate(companyId);
                if (template.logoUrl) {
                    logoSlot.innerHTML = `<img src="${escapeHtml(template.logoUrl)}" alt="Şirket logosu" style="width:100%;height:100%;object-fit:contain">`;
                } else {
                    renderFallback();
                }
            } catch {
                renderFallback();
            }
        };

        createStudioSelect?.addEventListener('change', () => handleAsync(loadLogo));
        handleAsync(loadLogo);
    };

    const bindCreateInfoStaffOptions = (overlay) => {
        const infoSelect = qs('[data-ticket-info-select]', overlay);
        const createStudioSelect = qs('[data-appointment-create-studio]', overlay);
        if (!infoSelect) return;

        const loadInfoStaff = async () => {
            const studioId = createStudioSelect?.value || studioSelect?.value || studioOptions[0]?.id;
            if (!studioId) {
                infoSelect.innerHTML = '<option value="">Infocu bulunamadı</option>';
                return;
            }

            infoSelect.disabled = true;
            infoSelect.innerHTML = '<option value="">Infocular yükleniyor...</option>';

            try {
                const payload = await apiFetch(`/users/options?roles=info&studio_id=${encodeURIComponent(studioId)}`);
                const users = uniqueById(payload.data || []);
                infoSelect.innerHTML = [
                    '<option value="">Infocu seçilmedi</option>',
                    ...users.map((user) => `<option value="${user.id}">${escapeHtml(user.name || user.email || `#${user.id}`)}</option>`),
                ].join('');
                infoSelect.disabled = false;
            } catch (error) {
                infoSelect.innerHTML = '<option value="">Infocu listesi alınamadı</option>';
                infoSelect.disabled = false;
            }
        };

        createStudioSelect?.addEventListener('change', () => handleAsync(loadInfoStaff));
        handleAsync(loadInfoStaff);
    };

    const bindOldCustomerLookup = (form) => {
        const panel = qs('[data-old-customer-panel]', form);
        const studioInput = qs('[name="studio_id"]', form);
        const phoneCodeInput = qs('[name="customer[phone_country_code]"]', form);
        const phoneInput = qs('[name="customer[phone_number]"]', form);
        const firstNameInput = qs('[name="customer[first_name]"]', form);
        const lastNameInput = qs('[name="customer[last_name]"]', form);
        const hotelInput = qs('[name="customer[hotel_name]"]', form);
        const roomInput = qs('[name="customer[room_number]"]', form);
        if (!panel || !studioInput || !phoneInput || !phoneCodeInput) return;

        let lookupTimer = null;
        let lastLookupKey = '';

        const setPanel = (html, visible = true) => {
            panel.style.display = visible ? 'block' : 'none';
            panel.innerHTML = html;
        };

        const fillInput = (input, value) => {
            if (input && value && !input.value) input.value = value;
        };

        const renderHistory = (data) => {
            const appointments = data.previous_appointments || [];
            if (!data.is_old_customer || appointments.length === 0) {
                setPanel(`
                    <div style="border:1px solid var(--border);background:var(--surface-soft);border-radius:0.9rem;padding:0.85rem 1rem;color:var(--text-muted);font-size:0.78rem">
                        Bu numara için seçili stüdyoda eski kayıt bulunamadı.
                    </div>
                `);
                return;
            }

            const rows = appointments.slice(0, 5).map((item) => `
                <div style="display:grid;grid-template-columns:1fr auto;gap:0.75rem;align-items:start;border-top:1px solid var(--border);padding-top:0.7rem;margin-top:0.7rem">
                    <div>
                        <div style="font-weight:800;color:var(--text-main);font-size:0.82rem">${escapeHtml(APPOINTMENT_TYPE_LABELS[item.appointment_type] || 'Kayıt')} · ${formatDateTime(item.appointment_at)}</div>
                        <div style="margin-top:0.22rem;color:var(--text-muted);font-size:0.74rem">${escapeHtml([item.hotel_name, item.room_number ? `Oda ${item.room_number}` : '', item.place].filter(Boolean).join(' · ') || 'Konum bilgisi yok')}</div>
                        ${ticketMetaLine(item) ? `<div style="margin-top:0.2rem;color:var(--text-subtle);font-size:0.72rem">${escapeHtml(ticketMetaLine(item))}</div>` : ''}
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.35rem">
                        <span class="${statusClass(item.status)}" style="font-size:0.62rem">${statusLabel(item.status)}</span>
                        ${item.price !== null && item.price !== undefined ? `<span class="badge-pill" style="font-size:0.62rem">${escapeHtml(item.price)} €</span>` : ''}
                    </div>
                </div>
            `).join('');

            setPanel(`
                <div style="border:1px solid rgba(34,197,94,.28);background:color-mix(in srgb, var(--surface-soft) 82%, rgba(34,197,94,.18));border-radius:1rem;padding:1rem">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap">
                        <div>
                            <div style="font-size:0.78rem;font-weight:900;color:var(--text-main)">Eski müşteri bulundu</div>
                            <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">${appointments.length} geçmiş kayıt listelendi. Bilgiler otomatik dolduruldu.</div>
                        </div>
                        <span class="badge-pill badge-pill--success">Eski Müşteri</span>
                    </div>
                    ${rows}
                </div>
            `);
        };

        const runLookup = async () => {
            const studioId = studioInput.value;
            const phoneCode = phoneCodeInput.value || '+90';
            const phoneNumber = phoneInput.value || '';
            const normalizedPhone = phoneNumber.replace(/\D+/g, '');
            if (!studioId || normalizedPhone.length < 5) {
                lastLookupKey = '';
                setPanel('', false);
                return;
            }

            const lookupKey = `${studioId}|${phoneCode}|${normalizedPhone}`;
            if (lookupKey === lastLookupKey) return;
            lastLookupKey = lookupKey;

            setPanel(`
                <div style="border:1px solid var(--border);background:var(--surface-soft);border-radius:0.9rem;padding:0.85rem 1rem;color:var(--text-muted);font-size:0.78rem">
                    Eski müşteri kayıtları kontrol ediliyor...
                </div>
            `);

            try {
                const payload = await apiFetch(`/studios/${studioId}/appointments/check-customer`, {
                    method: 'POST',
                    body: {
                        customer: {
                            first_name: firstNameInput?.value || '',
                            last_name: lastNameInput?.value || '',
                            phone_country_code: phoneCode,
                            phone_number: phoneNumber,
                            hotel_name: hotelInput?.value || '',
                        },
                    },
                });
                if (lookupKey !== `${studioInput.value}|${phoneCodeInput.value || '+90'}|${(phoneInput.value || '').replace(/\D+/g, '')}`) return;

                const data = payload.data || {};
                const customer = data.customer || {};
                fillInput(firstNameInput, customer.first_name);
                fillInput(lastNameInput, customer.last_name);
                fillInput(hotelInput, customer.hotel_name);
                fillInput(roomInput, customer.room_number);
                renderHistory(data);
            } catch (error) {
                setPanel(`
                    <div style="border:1px solid rgba(239,68,68,.28);background:rgba(239,68,68,.08);border-radius:0.9rem;padding:0.85rem 1rem;color:var(--danger);font-size:0.78rem">
                        Eski müşteri kontrolü yapılamadı: ${escapeHtml(error.message)}
                    </div>
                `);
            }
        };

        const scheduleLookup = () => {
            window.clearTimeout(lookupTimer);
            lookupTimer = window.setTimeout(() => handleAsync(runLookup), 350);
        };

        [phoneInput, phoneCodeInput, studioInput].forEach((input) => {
            input?.addEventListener('input', scheduleLookup);
            input?.addEventListener('change', scheduleLookup);
        });
    };

    const bindCreateAppointmentForm = (form, close) => {
        bindTicketFields(form);
        bindDesignerAppointmentFields(form);
        bindOldCustomerLookup(form);
        form?.addEventListener('submit', (e) => {
            e.preventDefault();
            handleAsync(async () => {
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
                close();
                await renderAppointments();
            });
        });
    };

    const openCreateAppointmentPopup = () => {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.64);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;padding:1.25rem';
        overlay.innerHTML = `
            <div style="width:min(96vw,1120px);min-height:70vh;max-height:92vh;overflow:auto;border:1px solid var(--border);background:var(--surface);border-radius:1.25rem;box-shadow:0 24px 70px rgba(2,6,23,.32)">
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;padding:1.25rem 1.35rem;background:linear-gradient(135deg, color-mix(in srgb, var(--primary) 16%, var(--surface)), var(--surface));border-bottom:1px solid var(--border)">
                    <div style="display:flex;align-items:center;gap:0.85rem">
                        <div data-appointment-create-logo style="width:46px;height:46px;border-radius:1rem;background:var(--surface);border:1px solid var(--border);display:grid;place-items:center;color:var(--primary);font-weight:900;overflow:hidden">
                            <span>${routeRecordType === 'tattoo' ? 'B' : 'R'}</span>
                        </div>
                        <div>
                        <div class="section-eyebrow">Yeni Kayıt</div>
                            <div class="section-title">${routeRecordType === 'tattoo' ? 'Bilet Aç' : 'Randevu Oluştur'}</div>
                        </div>
                    </div>
                    <button class="button-secondary" data-close-appointment-create style="padding:0.45rem 0.7rem">Kapat</button>
                </div>
                <div style="padding:1.35rem">
                    ${appointmentCreateFormMarkup()}
                </div>
            </div>
        `;
        const close = () => overlay.remove();
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) close();
        });
        qs('[data-close-appointment-create]', overlay)?.addEventListener('click', close);
        populateCreateStudios(overlay);
        bindCreatePopupLogo(overlay);
        bindCreateInfoStaffOptions(overlay);
        bindCreateAppointmentForm(qs('[data-appointment-create-form]', overlay), close);
        document.body.appendChild(overlay);
    };

    const endpointForRole = () => {
        if (isDriverRole()) return '/my-appointments';
        if (isArtistLikeRole()) return '/my-artist-appointments';
        if (!studioSelect?.value) return null;
        const params = new URLSearchParams();
        if (activeRecordType) params.set('appointment_type', activeRecordType);
        if (appointmentStatusFilter) params.set('status', appointmentStatusFilter);
        if (appointmentDateFrom) params.set('date_from', appointmentDateFrom);
        if (appointmentDateTo) params.set('date_to', appointmentDateTo);
        return `/studios/${studioSelect.value}/appointments${params.toString() ? `?${params.toString()}` : ''}`;
    };

    const renderImageThumb = (apt) => {
        const image = apt.customer?.photo_path || apt.photo_path || apt.source_image_path || apt.tattoo_image_paths?.[0];
        return image
            ? `<a href="${escapeHtml(image)}" target="_blank" rel="noopener noreferrer" style="flex-shrink:0"><img src="${escapeHtml(image)}" alt="Randevu görseli" style="width:42px;height:42px;object-fit:cover;border-radius:0.55rem;border:1px solid var(--border)"></a>`
            : '<div style="width:42px;height:42px;border-radius:0.55rem;border:1px solid var(--border);background:var(--surface-soft);flex-shrink:0"></div>';
    };

    const appointmentSearchHaystack = (apt) => [
        apt.id,
        apt.customer?.first_name,
        apt.customer?.last_name,
        apt.customer?.phone_country_code,
        apt.customer?.phone_number,
        apt.customer?.hotel_name,
        apt.customer?.room_number,
        apt.customer?.customer_notes,
        apt.place,
        apt.notes,
        apt.status,
        statusLabel(apt.status),
        apt.appointment_type,
        APPOINTMENT_TYPE_LABELS[apt.appointment_type],
        apt.ticket_type_labels?.join(' '),
        apt.ticket_types?.join(' '),
        apt.tattoo_type_label,
        apt.tattoo_type,
        apt.payment_method_label,
        apt.payment_method,
        apt.artist?.name,
        apt.studio?.name,
        apt.studio?.company?.name,
        ticketMetaLine(apt),
    ].filter((value) => value !== null && value !== undefined)
        .join(' ')
        .toLocaleLowerCase('tr-TR');

    const renderAppointments = async () => {
        const endpoint = endpointForRole();
        if (!endpoint) {
            listNode.innerHTML = '<div class="empty-state">Kayıtları görüntülemek için bir stüdyo seçin.</div>';
            return;
        }

        listNode.innerHTML = skeletonGrid(4);
        const payload = await apiFetch(endpoint);
        const now = Date.now();
        const search = appointmentSearchQuery.trim().toLocaleLowerCase('tr-TR');
        const appointments = (payload.data || []).filter((apt) => {
            const typeMatches = !activeRecordType || apt.appointment_type === activeRecordType;
            const dateMs = apt.appointment_at ? new Date(apt.appointment_at).getTime() : null;
            const timeMatches = activeTimeScope === 'all'
                || (activeTimeScope === 'upcoming' ? (dateMs === null || dateMs >= now) : (dateMs !== null && dateMs < now));
            const statusMatches = !appointmentStatusFilter || apt.status === appointmentStatusFilter;
            const searchMatches = !search || appointmentSearchHaystack(apt).includes(search);
            return typeMatches && timeMatches && statusMatches && searchMatches;
        });
        lastRenderedAppointments = appointments;

        listNode.innerHTML = appointments.length
            ? appointments.map((apt, i) => {
                const customerName = `${apt.customer?.first_name || ''} ${apt.customer?.last_name || ''}`.trim();
                const phone = `${apt.customer?.phone_country_code || ''}${apt.customer?.phone_number || ''}`.replace(/\s+/g, '');
                const studioId = apt.studio?.id || studioSelect?.value || '';
                const limited = apt.artist_limited_view;
                const metaLine = ticketMetaLine(apt);
                const assignmentLabel = assignmentStatusLabel(apt);
                const ticketTimePending = apt.appointment_type === 'tattoo'
                    && apt.appointment_at
                    && new Date(apt.appointment_at).getTime() > Date.now();
                return `
                <article class="list-card animate-stagger-${(i % 3) + 1}" data-appointment-id="${apt.id}" data-studio-id="${studioId}" data-ticket-time-pending="${ticketTimePending ? '1' : '0'}" style="padding:0.62rem 0.78rem">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem">
                        <div style="display:flex;align-items:center;gap:0.65rem;min-width:0;flex:1">
                            ${renderImageThumb(apt)}
                            <div style="min-width:0">
                                <div style="font-size:0.82rem;font-weight:700;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(limited ? 'Atanan bilet' : (customerName || 'İsimsiz'))}</div>
                                <div style="margin-top:0.2rem;font-size:0.7rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    ${escapeHtml(formatDateTime(apt.appointment_at))} · ${escapeHtml(limited ? (apt.studio?.name || 'Stüdyo') : (apt.customer?.hotel_name || apt.place || '—'))}${limited ? '' : ` · Oda ${escapeHtml(apt.customer?.room_number || '—')}`}
                                </div>
                                ${metaLine ? `<div style="margin-top:0.14rem;font-size:0.68rem;color:var(--text-subtle);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(metaLine)}</div>` : ''}
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.38rem;flex-wrap:wrap;flex-shrink:0">
                            <span class="${statusClass(apt.status)}" style="font-size:0.58rem;padding:0.22rem 0.44rem">${statusLabel(apt.status)}</span>
                            ${assignmentLabel ? `<span class="${assignmentStatusClass(apt)}" style="font-size:0.58rem;padding:0.22rem 0.44rem">${escapeHtml(assignmentLabel)}</span>` : ''}
                            ${apt.appointment_type ? `<span class="badge-pill ${apt.appointment_type === 'tattoo' ? 'badge-pill--purple' : 'badge-pill--teal'}" style="font-size:0.58rem;padding:0.22rem 0.44rem">${APPOINTMENT_TYPE_LABELS[apt.appointment_type] ?? apt.appointment_type}</span>` : ''}
                            ${apt.info_staff?.name ? `<span class="badge-pill" style="font-size:0.58rem;padding:0.22rem 0.44rem">Info: ${escapeHtml(apt.info_staff.name)}</span>` : ''}
                            ${apt.appointment_type === 'tattoo' && apt.price !== null && apt.price !== undefined && String(apt.price).trim() !== '' ? `<span class="badge-pill" style="font-size:0.58rem;padding:0.22rem 0.44rem">${escapeHtml(apt.price)} €</span>` : ''}
                            <a href="/admin/appointments/${apt.id}" class="button-ghost" style="padding:0.32rem 0.58rem;font-size:0.68rem">Detay</a>
                            ${apt.appointment_type === 'tattoo' ? `<button class="button-secondary" data-ticket-print="${apt.id}" style="padding:0.32rem 0.58rem;font-size:0.68rem">Yazdır</button>` : ''}
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-top:${isDriverRole() || (adminConfig.role === 'artist' && apt.appointment_type === 'tattoo' && !['completed', 'cancelled'].includes(apt.status)) ? '0.55rem' : '0'}">
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
            : `<div class="empty-state">${appointmentSearchQuery.trim() ? 'Aramanıza uygun kayıt bulunamadı.' : `Bu kapsamda ${activeRecordType === 'tattoo' ? 'bilet' : 'randevu'} bulunmuyor.`}</div>`;

        listNode.querySelectorAll('[data-ticket-print]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-ticket-print');
                const appointment = lastRenderedAppointments.find((item) => String(item.id) === String(id));
                handleAsync(() => openTicketPdfPrintWindow(appointment || sampleTicketPdfAppointment(), companyIdForAppointment(appointment)));
            });
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

    root.querySelectorAll('[data-appointments-type-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            activeRecordType = button.getAttribute('data-appointments-type-tab') || 'designer';
            syncAppointmentTabs();
            handleAsync(renderAppointments);
        });
    });
    root.querySelectorAll('[data-appointments-time-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            activeTimeScope = button.getAttribute('data-appointments-time-tab') || 'upcoming';
            syncAppointmentTabs();
            handleAsync(renderAppointments);
        });
    });
    qs('[data-appointments-filter]', root)?.addEventListener('click', openAppointmentFilterPopup);
    qs('[data-open-appointment-create]', root)?.addEventListener('click', openCreateAppointmentPopup);
    studioSelect?.addEventListener('change', () => handleAsync(renderAppointments));
    qs('[data-appointments-refresh]', root)?.addEventListener('click', () => handleAsync(renderAppointments));
    const searchInput = qs('[data-appointments-search]', root);
    let searchTimer = null;
    searchInput?.addEventListener('input', () => {
        appointmentSearchQuery = searchInput.value || '';
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => handleAsync(renderAppointments), 220);
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
                    <div class="form-grid form-grid--split">
                        <div class="field-wrap"><label class="field-label">Instagram <span style="color:var(--text-subtle)">(opsiyonel)</span></label><input class="field-input" name="instagram" placeholder="soulofink.gundogdu"></div>
                        <div class="field-wrap"><label class="field-label">Facebook <span style="color:var(--text-subtle)">(opsiyonel)</span></label><input class="field-input" name="facebook" placeholder="soulofink.gundogdu"></div>
                    </div>
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
                    ${statBlock('Sosyal', [studio.instagram ? `Instagram: ${escapeHtml(studio.instagram)}` : '', studio.facebook ? `Facebook: ${escapeHtml(studio.facebook)}` : ''].filter(Boolean).join('<br>') || '—')}
                </div>
                <div style="padding-top:1.1rem;border-top:1px solid var(--border)">
                    <form class="form-grid" data-studio-form data-studio-id="${studio.id}">
                        <div class="form-grid form-grid--split">
                            <div class="field-wrap"><label class="field-label">Stüdyo Adı</label><input class="field-input" name="name" value="${escapeHtml(studio.name)}"></div>
                            <div class="field-wrap"><label class="field-label">Konum</label><input class="field-input" name="location" value="${escapeHtml(studio.location || '')}"></div>
                        </div>
                        <div class="form-grid form-grid--split">
                            <div class="field-wrap"><label class="field-label">Instagram</label><input class="field-input" name="instagram" value="${escapeHtml(studio.instagram || '')}" placeholder="soulofink.gundogdu"></div>
                            <div class="field-wrap"><label class="field-label">Facebook</label><input class="field-input" name="facebook" value="${escapeHtml(studio.facebook || '')}" placeholder="soulofink.gundogdu"></div>
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
        const requests = (payload.data || []).filter((request) => request.status === 'pending');

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
                await apiFetch(`/appointment-requests/${card?.getAttribute('data-request-id')}/accept`, {
                    method: 'PATCH',
                    body: {},
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
    const canSendTestNotification = adminConfig.isAdmin || adminConfig.role === 'admin';

    root.innerHTML = `
        ${pageHeader('Ayarlar', 'Uygulama Ayarları', canSendTestNotification ? 'Web panel teması ve bildirim testi.' : 'Web panel teması.', '<span class="badge-pill">Web</span>')}
        <div class="data-grid">
            <div class="panel-card">
                <div class="section-title" style="margin-bottom:0.75rem">Tema</div>
                <p style="font-size:0.78rem;color:var(--text-muted);line-height:1.55">Web panel mevcut sistem temasını kullanır. Mobil uygulamada varsayılan light, kullanıcı seçerse dark kalır.</p>
            </div>
            ${canSendTestNotification ? `<div class="panel-card">
                <div class="section-title" style="margin-bottom:0.75rem">Bildirim Testi</div>
                <button class="button-primary" data-test-notification style="justify-content:center">Test Bildirimi Gönder</button>
            </div>` : ''}
        </div>
    `;
    qs('[data-test-notification]', root)?.addEventListener('click', () => handleAsync(async () => {
        await apiFetch('/notifications/test', { method: 'POST', body: {} });
        showToast('Test bildirimi tetiklendi.', 'success');
    }));
};

const renderTicketPdfTemplatePage = async (root) => {
    if (!['admin', 'yonetici'].includes(adminConfig.role)) {
        root.innerHTML = `
            ${pageHeader('PDF Şablonu', 'Yetkisiz Alan', 'Bilet PDF metinleri ve logoları yalnızca admin veya şirket yöneticisi tarafından düzenlenebilir.', '<span class="badge-pill badge-pill--danger">403</span>')}
            <div class="empty-state">Bu sayfayı görüntüleme yetkiniz yok.</div>
        `;
        return;
    }

    const canSelectCompany = adminConfig.isAdmin || adminConfig.role === 'admin';

    root.innerHTML = `
        ${pageHeader('PDF Şablonu', 'Bilet PDF Ayarları', 'Şirket bazlı logo, footer ve dil metinlerini buradan düzenleyin.', '<span class="badge-pill badge-pill--purple">Bilet PDF</span>')}
        <div class="panel-card">
            <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
                ${canSelectCompany ? `<div class="field-wrap" style="min-width:min(100%,320px);flex:1">
                    <label class="field-label">Şirket</label>
                    <select class="field-select" data-ticket-pdf-company></select>
                </div>` : `<div class="field-wrap" style="min-width:min(100%,320px);flex:1">
                    <label class="field-label">Şirket</label>
                    <div class="badge-pill" data-ticket-pdf-company-name style="width:max-content;max-width:100%">Şirket yükleniyor...</div>
                </div>`}
                <button class="button-primary" data-ticket-pdf-edit style="padding:0.58rem 0.95rem">PDF Şablonunu Düzenle</button>
            </div>
            <div data-ticket-pdf-summary>${skeletonGrid(1)}</div>
        </div>
    `;

    const companySelect = qs('[data-ticket-pdf-company]', root);
    const companyNameBadge = qs('[data-ticket-pdf-company-name]', root);
    const summaryNode = qs('[data-ticket-pdf-summary]', root);
    let companies = [];
    let currentTemplate = null;

    const selectedCompanyId = () => canSelectCompany ? (companySelect?.value || companies[0]?.id || null) : (companies[0]?.id || null);

    const renderSummary = () => {
        if (!currentTemplate) {
            summaryNode.innerHTML = '<div class="empty-state">PDF şablonu yüklenemedi.</div>';
            return;
        }
        const logo = currentTemplate.logoUrl || '';
        summaryNode.innerHTML = `
            <div style="display:grid;grid-template-columns:96px 1fr;gap:1rem;align-items:center">
                <div style="width:96px;height:96px;border-radius:0.85rem;border:1px solid var(--border);background:var(--surface-soft);display:grid;place-items:center;overflow:hidden">
                    ${logo ? `<img src="${escapeHtml(logo)}" alt="PDF logo" style="width:100%;height:100%;object-fit:contain">` : '<span style="font-size:0.72rem;color:var(--text-subtle)">Logo yok</span>'}
                </div>
                <div>
                    <div style="font-weight:800;color:var(--text-main);font-size:1rem">${escapeHtml(currentTemplate.brandTitle || 'PDF Şablonu')}</div>
                    <div style="margin-top:0.3rem;color:var(--text-muted);font-size:0.82rem">${escapeHtml(currentTemplate.brandSubtitle || '')}</div>
                    <div style="margin-top:0.55rem;display:flex;gap:0.45rem;flex-wrap:wrap">
                        <span class="badge-pill">Varsayılan dil: ${escapeHtml((currentTemplate.language || 'de').toUpperCase())}</span>
                        <span class="badge-pill">Logo ve metinler şirket bazlı</span>
                    </div>
                    <div style="margin-top:0.9rem;display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap">
                        <label class="button-secondary" style="padding:0.5rem 0.85rem;font-size:0.78rem;cursor:pointer">
                            Logo Dosyası Seç
                            <input type="file" accept="image/*" data-ticket-pdf-logo-direct style="display:none">
                        </label>
                        <span style="font-size:0.72rem;color:var(--text-subtle)">PNG, JPG veya WEBP yükleyebilirsiniz.</span>
                    </div>
                </div>
            </div>
        `;
        qs('[data-ticket-pdf-logo-direct]', summaryNode)?.addEventListener('change', (event) => {
            const file = event.target.files?.[0];
            if (!file) return;
            handleAsync(async () => {
                const companyId = selectedCompanyId();
                if (!companyId) throw new Error('Şirket seçin.');
                const logoUrl = await uploadTicketPdfLogo(companyId, file);
                if (logoUrl) {
                    showToast('PDF logosu yüklendi.', 'success');
                    await loadTemplate();
                }
            });
        });
    };

    const loadTemplate = async () => {
        const companyId = selectedCompanyId();
        if (!companyId) {
            summaryNode.innerHTML = '<div class="empty-state">Şirket bulunamadı.</div>';
            return;
        }
        summaryNode.innerHTML = skeletonGrid(1);
        currentTemplate = await fetchTicketPdfTemplate(companyId);
        renderSummary();
    };

    const payload = await apiFetch('/companies');
    companies = payload.data || [];
    if (canSelectCompany && companySelect) {
        companySelect.innerHTML = companies.map((company) => `<option value="${company.id}">${escapeHtml(company.name)}</option>`).join('');
    } else if (companyNameBadge) {
        companyNameBadge.textContent = companies[0]?.name || 'Şirket bulunamadı';
    }
    companySelect?.addEventListener('change', () => handleAsync(loadTemplate));
    qs('[data-ticket-pdf-edit]', root)?.addEventListener('click', () => {
        handleAsync(async () => {
            await openTicketPdfTemplateEditor(selectedCompanyId(), sampleTicketPdfAppointment());
            await loadTemplate();
        });
    });
    await loadTemplate();
};

/* ── Hakedişler ─────────────────────────────────────────────── */

const renderEarningsPage = async (root) => {
    const canManage = ['admin', 'yonetici'].includes(adminConfig.role);
    const hasPersonalEarnings = ['supervisor', 'artist', 'designer', 'info', 'sofor', 'calisan'].includes(adminConfig.role);
    const locksToOwnStudio = false;
    let managing = canManage && !hasPersonalEarnings;
    let companies = [];
    let studios = [];
    let selectedCompanyId = '';
    let selectedStudioId = '';
    let selectedStaffId = '';
    let selectedStatus = '';
    let dateFrom = '';
    let dateTo = '';
    let latestEarningsData = {};
    const normalizeDateRange = () => {
        if (dateFrom && dateTo && dateFrom > dateTo) {
            const nextFrom = dateTo;
            dateTo = dateFrom;
            dateFrom = nextFrom;
        }
    };
    const dateRangeBadge = () => {
        if (!dateFrom && !dateTo) return '';
        const label = dateFrom && dateTo
            ? `${dateFrom} - ${dateTo}`
            : (dateFrom ? `${dateFrom} sonrası` : `${dateTo} öncesi`);
        return `<span class="badge-pill badge-pill--teal">Tarih: ${escapeHtml(label)}</span>`;
    };

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
                    ${adminConfig.isAdmin ? `
                        <div class="field-wrap" data-earnings-company-wrap style="min-width:min(100%,260px);${managing && !locksToOwnStudio ? '' : 'display:none'}">
                            <label class="field-label">Şirket</label>
                            <select class="field-select" data-earnings-company></select>
                        </div>
                    ` : ''}
                    <div class="field-wrap" data-earnings-studio-wrap style="min-width:min(100%,280px);${managing && !locksToOwnStudio ? '' : 'display:none'}">
                        <label class="field-label">Stüdyo</label>
                        <select class="field-select" data-earnings-studio></select>
                    </div>
                    <button class="button-primary" data-open-earnings-filter style="${managing && !locksToOwnStudio ? '' : 'display:none'};align-self:end">Filtrele</button>
                    ${locksToOwnStudio ? '<div class="badge-pill" data-earnings-locked-studio style="display:none">Stüdyo yükleniyor...</div>' : ''}
                ` : ''}
            </div>
            <div data-earnings-content style="margin-top:1rem">${skeletonGrid(5)}</div>
        </div>
    `;

    const content = qs('[data-earnings-content]', root);
    const companyWrap = qs('[data-earnings-company-wrap]', root);
    const companySelect = qs('[data-earnings-company]', root);
    const studioWrap = qs('[data-earnings-studio-wrap]', root);
    const studioSelect = qs('[data-earnings-studio]', root);
    const filterButton = qs('[data-open-earnings-filter]', root);
    const lockedStudioLabel = qs('[data-earnings-locked-studio]', root);

    const studioCompanyId = (studio = {}) => String(studio.company?.id ?? studio.company_id ?? '');
    const filteredStudios = () => adminConfig.isAdmin && selectedCompanyId
        ? studios.filter((studio) => studioCompanyId(studio) === String(selectedCompanyId))
        : studios;
    const syncStudioOptions = () => {
        const visibleStudios = adminConfig.isAdmin && !selectedCompanyId ? [] : filteredStudios();
        if (!visibleStudios.some((studio) => String(studio.id) === String(selectedStudioId))) {
            selectedStudioId = '';
        }
        if (studioSelect) {
            const placeholder = adminConfig.isAdmin && !selectedCompanyId
                ? '<option value="">Önce şirket seçin</option>'
                : '<option value="">Stüdyo seçin</option>';
            studioSelect.innerHTML = placeholder + visibleStudios.map((studio) =>
                `<option value="${studio.id}">${escapeHtml(studio.name)}</option>`
            ).join('');
            studioSelect.value = selectedStudioId;
            studioSelect.disabled = adminConfig.isAdmin && !selectedCompanyId;
        }
        if (filterButton) filterButton.disabled = !selectedStudioId;
        if (lockedStudioLabel) {
            lockedStudioLabel.textContent = visibleStudios[0]?.name
                ? `Stüdyo: ${visibleStudios[0].name}`
                : 'Atanmış stüdyo bulunamadı';
        }
    };

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

    const filterTotalBanner = (data = {}) => {
        const summary = data.summary || {};
        const staff = data.staff || [];
        const selectedStudio = studios.find((studio) => String(studio.id) === String(selectedStudioId));
        const selectedCompany = companies.find((company) => String(company.id) === String(selectedCompanyId));
        const selectedStaff = staff.find((person) => String(person.id) === String(selectedStaffId));
        const filters = [
            managing && selectedCompany ? `Şirket: ${selectedCompany.name || 'Şirket'}` : '',
            managing && selectedStudio ? `Stüdyo: ${selectedStudio.name || 'Stüdyo'}` : '',
            managing && selectedStaff ? `Personel: ${selectedStaff.name || 'Personel'}` : '',
            selectedStatus ? `Durum: ${selectedStatus === 'pending' ? 'Ödenmeyen' : selectedStatus === 'paid' ? 'Ödenen' : 'Tümü'}` : 'Durum: Tümü',
            dateFrom || dateTo ? `Tarih: ${dateFrom && dateTo ? `${dateFrom} - ${dateTo}` : dateFrom ? `${dateFrom} sonrası` : `${dateTo} öncesi`}` : '',
        ].filter(Boolean);

        return `
            <article class="metric-card" style="margin-bottom:1rem;border-color:var(--success)">
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap">
                    <div>
                        <div class="section-eyebrow" style="color:var(--success)">Seçili Filtre Toplam Ödenecek</div>
                        <div class="earnings-metric-value">${formatMoney(summary.pending_total)}</div>
                        <div class="earnings-metric-helper">${Number(summary.pending_count || 0)} bekleyen kayıt</div>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(130px,1fr));gap:0.55rem;min-width:min(100%,300px)">
                        ${statBlock('Ödenen', formatMoney(summary.paid_total))}
                        ${statBlock('Genel Toplam', formatMoney(summary.filter_total_payment ?? summary.total))}
                    </div>
                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;justify-content:flex-end;width:100%">
                        ${filters.map((filter) => `<span class="badge-pill">${escapeHtml(filter)}</span>`).join('')}
                        <span class="badge-pill">${Number(summary.earning_count || 0)} hakediş kaydı</span>
                    </div>
                </div>
            </article>
        `;
    };

    const earningCard = (earning, showUser = false, allowPaidAction = managing) => {
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
                <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-top:0.75rem">
                    ${earning.appointment_id ? `
                        <a href="/admin/appointments/${earning.appointment_id}" class="button-secondary" style="padding:0.45rem 0.8rem;font-size:0.75rem;text-decoration:none">Detay</a>
                    ` : '<span></span>'}
                    ${allowPaidAction && managing && !paid ? `
                        <button class="button-primary" data-mark-earning-paid style="padding:0.45rem 0.8rem;font-size:0.75rem">Ödendi Olarak İşaretle</button>
                    ` : ''}
                </div>
            </article>
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
            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-top:0.8rem">
                ${dateRangeBadge()}
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                    <button class="button-primary" data-earnings-apply-filters>Filtrele</button>
                    <button class="button-secondary" data-earnings-clear-filters>Filtreleri Temizle</button>
                </div>
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

    const openManagementFilterPopup = () => {
        if (!selectedStudioId) {
            showToast('Önce stüdyo seçin.', 'warning');
            return;
        }

        const staff = latestEarningsData.staff || [];
        const selectedPersonSummary = (personId, data = latestEarningsData) => {
            const person = (data.staff || []).find((item) => String(item.id) === String(personId));
            if (!person) return '';

            return `
                <article class="list-card" style="padding:0.85rem 0.95rem;margin-top:0.9rem">
                    <div style="display:flex;justify-content:space-between;gap:0.8rem;align-items:flex-start;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--text-main)">${escapeHtml(person.name || 'Personel')}</div>
                            <div style="margin-top:0.18rem;font-size:0.72rem;color:var(--text-muted)">${escapeHtml(roleLabel(person.role))} · ${Number(person.earning_count || 0)} kayıt</div>
                        </div>
                        <span class="badge-pill ${roleBadgeClass(person.role)}">${escapeHtml(roleLabel(person.role))}</span>
                    </div>
                    <div class="earnings-detail-grid">
                        ${statBlock('Bekleyen', formatMoney(person.pending_total))}
                        ${statBlock('Ödenen', formatMoney(person.paid_total))}
                        ${statBlock('Komisyon', `%${Number(person.commission_rate || 0).toLocaleString('tr-TR')}`)}
                    </div>
                </article>
            `;
        };
        const popupResultHtml = (data = {}, filters = {}) => {
            const summary = data.summary || {};
            const earnings = data.earnings || [];
            const selectedPerson = (data.staff || []).find((person) => String(person.id) === String(filters.staffId));
            const filterChips = [
                selectedPerson ? `Kullanıcı: ${selectedPerson.name || 'Personel'}` : 'Kullanıcı: Tümü',
                filters.status ? `Durum: ${filters.status === 'pending' ? 'Ödenmeyen' : 'Ödenen'}` : 'Durum: Tümü',
                filters.dateFrom || filters.dateTo
                    ? `Tarih: ${filters.dateFrom && filters.dateTo ? `${filters.dateFrom} - ${filters.dateTo}` : filters.dateFrom ? `${filters.dateFrom} sonrası` : `${filters.dateTo} öncesi`}`
                    : '',
            ].filter(Boolean);

            return `
                <div style="margin-top:1rem;border-top:1px solid var(--border);padding-top:1rem">
                    <div style="display:flex;justify-content:space-between;gap:0.75rem;align-items:center;flex-wrap:wrap;margin-bottom:0.75rem">
                        <div>
                            <div class="section-eyebrow">Liste Sonucu</div>
                            <div class="section-title">Hakediş Detayları</div>
                        </div>
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;justify-content:flex-end">
                            ${filterChips.map((chip) => `<span class="badge-pill">${escapeHtml(chip)}</span>`).join('')}
                        </div>
                    </div>
                    <div class="earnings-metrics">
                        <article class="metric-card">
                            <div class="section-eyebrow" style="color:var(--warning)">Toplam Ödenecek</div>
                            <div class="earnings-metric-value">${formatMoney(summary.pending_total)}</div>
                            <div class="earnings-metric-helper">${Number(summary.pending_count || 0)} bekleyen kayıt</div>
                        </article>
                        <article class="metric-card">
                            <div class="section-eyebrow" style="color:var(--success)">Ödenen</div>
                            <div class="earnings-metric-value">${formatMoney(summary.paid_total)}</div>
                            <div class="earnings-metric-helper">${Number(summary.paid_count || 0)} tamamlandı</div>
                        </article>
                        <article class="metric-card">
                            <div class="section-eyebrow">Genel Toplam</div>
                            <div class="earnings-metric-value">${formatMoney(summary.filter_total_payment ?? summary.total)}</div>
                            <div class="earnings-metric-helper">${Number(summary.earning_count || 0)} hakediş kaydı</div>
                        </article>
                    </div>
                    ${filters.staffId ? selectedPersonSummary(filters.staffId, data) : ''}
                    <div class="list-stack" style="margin-top:0.85rem">
                        ${earnings.map((earning) => earningCard(earning, true, false)).join('') || '<div class="empty-state">Bu filtreye uygun hakediş kaydı bulunmuyor.</div>'}
                    </div>
                </div>
            `;
        };
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.48);display:flex;align-items:center;justify-content:center;padding:1rem';
        overlay.innerHTML = `
            <div class="panel-card" style="width:min(100%,860px);max-height:90vh;overflow:auto;padding:1.2rem">
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:1rem">
                    <div>
                        <div class="section-eyebrow">Hakediş Filtresi</div>
                        <div class="section-title">Listeleme Seçenekleri</div>
                    </div>
                    <button class="button-secondary" data-close-earnings-popup style="padding:0.45rem 0.7rem">Kapat</button>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:0.8rem">
                    <div class="field-wrap">
                        <label class="field-label">Kullanıcı</label>
                        <select class="field-select" data-popup-earnings-staff>
                            <option value="">Tüm kullanıcılar</option>
                            ${staff.map((person) => `
                                <option value="${person.id}" ${String(person.id) === String(selectedStaffId) ? 'selected' : ''}>
                                    ${escapeHtml(`${person.name || 'Personel'} — ${roleLabel(person.role)} — Bekleyen ${formatMoney(person.pending_total)}`)}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Durum</label>
                        <select class="field-select" data-popup-earnings-status>
                            <option value="" ${selectedStatus === '' ? 'selected' : ''}>Tümü</option>
                            <option value="pending" ${selectedStatus === 'pending' ? 'selected' : ''}>Ödenmeyen</option>
                            <option value="paid" ${selectedStatus === 'paid' ? 'selected' : ''}>Ödenen</option>
                        </select>
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Başlangıç</label>
                        <input class="field-input" type="date" value="${escapeHtml(dateFrom)}" data-popup-earnings-date-from>
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Bitiş</label>
                        <input class="field-input" type="date" value="${escapeHtml(dateTo)}" data-popup-earnings-date-to>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:0.6rem;flex-wrap:wrap;margin-top:1rem">
                    <button class="button-secondary" data-popup-clear-earnings>Temizle</button>
                    <button class="button-primary" data-popup-list-earnings>Listele</button>
                </div>
                <div data-popup-earnings-result>
                    <div class="empty-state" style="margin-top:1rem">Filtreleri seçip Listele butonuna basınca sonuçlar burada görünecek.</div>
                </div>
            </div>
        `;

        const close = () => overlay.remove();
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) close();
        });
        qs('[data-close-earnings-popup]', overlay)?.addEventListener('click', close);
        qs('[data-popup-clear-earnings]', overlay)?.addEventListener('click', () => {
            qs('[data-popup-earnings-staff]', overlay).value = '';
            qs('[data-popup-earnings-status]', overlay).value = '';
            qs('[data-popup-earnings-date-from]', overlay).value = '';
            qs('[data-popup-earnings-date-to]', overlay).value = '';
            qs('[data-popup-earnings-result]', overlay).innerHTML = '<div class="empty-state" style="margin-top:1rem">Filtreler temizlendi. Listelemek için tekrar Listele butonuna bas.</div>';
        });
        qs('[data-popup-list-earnings]', overlay)?.addEventListener('click', () => handleAsync(async () => {
            const popupStaffId = qs('[data-popup-earnings-staff]', overlay)?.value || '';
            const popupStatus = qs('[data-popup-earnings-status]', overlay)?.value || '';
            let popupDateFrom = qs('[data-popup-earnings-date-from]', overlay)?.value || '';
            let popupDateTo = qs('[data-popup-earnings-date-to]', overlay)?.value || '';
            if (popupDateFrom && popupDateTo && popupDateFrom > popupDateTo) {
                const nextFrom = popupDateTo;
                popupDateTo = popupDateFrom;
                popupDateFrom = nextFrom;
            }

            const resultNode = qs('[data-popup-earnings-result]', overlay);
            resultNode.innerHTML = skeletonGrid(3);
            const params = new URLSearchParams();
            if (popupStaffId) params.set('user_id', popupStaffId);
            if (popupStatus) params.set('status', popupStatus);
            if (popupDateFrom) params.set('date_from', popupDateFrom);
            if (popupDateTo) params.set('date_to', popupDateTo);

            const payload = await apiFetch(`/studios/${selectedStudioId}/earnings${params.toString() ? `?${params.toString()}` : ''}`);
            resultNode.innerHTML = popupResultHtml(payload.data || {}, {
                staffId: popupStaffId,
                status: popupStatus,
                dateFrom: popupDateFrom,
                dateTo: popupDateTo,
            });
        }));

        document.body.appendChild(overlay);
    };

    const bindFilterActions = () => {
        qs('[data-earnings-status-filter]', root)?.addEventListener('change', (event) => {
            selectedStatus = event.target.value;
            handleAsync(load);
        });
        qs('[data-earnings-date-from]', root)?.addEventListener('change', (event) => {
            dateFrom = event.target.value;
        });
        qs('[data-earnings-date-to]', root)?.addEventListener('change', (event) => {
            dateTo = event.target.value;
        });
        qs('[data-earnings-apply-filters]', root)?.addEventListener('click', () => {
            handleAsync(load);
        });
        qs('[data-earnings-clear-filters]', root)?.addEventListener('click', () => {
            selectedStaffId = '';
            selectedStatus = '';
            dateFrom = '';
            dateTo = '';
            handleAsync(load);
        });
    };

    const load = async () => {
        if (managing && adminConfig.isAdmin && !selectedCompanyId) {
            content.innerHTML = '<div class="empty-state">Hakedişleri görmek için önce şirket seçin.</div>';
            return;
        }

        if (managing && !selectedStudioId) {
            content.innerHTML = `<div class="empty-state">${adminConfig.isAdmin && selectedCompanyId ? 'Seçili şirket için stüdyo seçin.' : 'Hakedişleri görmek için stüdyo seçin.'}</div>`;
            return;
        }

        content.innerHTML = skeletonGrid(5);
        normalizeDateRange();
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
        latestEarningsData = data;
        if (filterButton) filterButton.disabled = !selectedStudioId;

        content.innerHTML = `
            ${filterTotalBanner(data)}
            ${summaryCards(data.summary)}
            ${managing ? '' : personalFilters()}
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
        const [studioPayload, companyPayload] = await Promise.all([
            apiFetch('/studios/options'),
            adminConfig.isAdmin ? apiFetch('/companies') : Promise.resolve({ data: [] }),
        ]);
        studios = uniqueById(studioPayload.data || []);
        companies = uniqueById(companyPayload.data || []);

        if (companySelect) {
            companySelect.innerHTML = '<option value="">Şirket seçin</option>' + companies.map((company) =>
                `<option value="${company.id}">${escapeHtml(company.name)}</option>`
            ).join('');
            selectedCompanyId = '';
            companySelect.value = selectedCompanyId;
            companySelect.addEventListener('change', () => {
                selectedCompanyId = companySelect.value;
                selectedStaffId = '';
                selectedStatus = '';
                dateFrom = '';
                dateTo = '';
                syncStudioOptions();
                handleAsync(load);
            });
        }

        syncStudioOptions();
        filterButton?.addEventListener('click', openManagementFilterPopup);
        if (studioSelect) {
            studioSelect.addEventListener('change', () => {
                selectedStudioId = studioSelect.value;
                selectedStaffId = '';
                selectedStatus = '';
                dateFrom = '';
                dateTo = '';
                if (filterButton) filterButton.disabled = !selectedStudioId;
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
            if (companyWrap) companyWrap.style.display = managing && !locksToOwnStudio ? '' : 'none';
            if (studioWrap) studioWrap.style.display = managing && !locksToOwnStudio ? '' : 'none';
            if (filterButton) filterButton.style.display = managing && !locksToOwnStudio ? '' : 'none';
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
    ['[data-admin-ticket-pdf-template]', renderTicketPdfTemplatePage],
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
