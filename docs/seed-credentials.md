# Test Ortamı — Giriş Bilgileri

> **Tüm hesaplarda şifre:** `123456`
>
> Seeder komutunu çalıştır: `php artisan migrate:fresh --seed`

---

## Platform Yöneticileri

| Rol | Email | Yetki |
|-----|-------|-------|
| **Admin** | `admin@example.com` | Tüm sistemi yönetir; tüm şirket/dükkan/stüdyo/randevulara erişir |
| **Yönetici** | `yonetici@example.com` | Şirkete bağlı dükkan ve stüdyoları yönetir |

---

## Stüdyo Ekipleri

### Stüdyo 1 — Ink Empire Kadıköy Tattoo

| Rol | Email | Stüdyodaki Görevi |
|-----|-------|-------------------|
| **StudioAdmin** | `studioadmin1@example.com` | Stüdyo yöneticisi |
| **Supervisor** | `supervisor1@example.com` | Operasyon süpervizörü |
| **Designer** | `designer1@example.com` | Geometrik & minimalist tasarım |
| **Artist** | `artist1@example.com` | Realistik portre |
| **Dövmeci** | `dovmeci1@example.com` | Traditional & Neo-traditional |
| **Info** | `info1@example.com` | Müşteri kabul / randevu girişi |
| **Şoför** | `sofor1@example.com` | Transfer sorumlusu |
| **Çalışan** | `calisan@example.com` | Genel çalışan |

### Stüdyo 2 — Ink Empire Beşiktaş Tattoo

| Rol | Email | Stüdyodaki Görevi |
|-----|-------|-------------------|
| **StudioAdmin** | `studioadmin2@example.com` | Stüdyo yöneticisi |
| **Supervisor** | `supervisor2@example.com` | Operasyon süpervizörü |
| **Designer** | `designer2@example.com` | Watercolor & abstract tasarım |
| **Artist** | `artist2@example.com` | Fine line & blackwork |
| **Dövmeci** | `dovmeci2@example.com` | Japanese & Irezumi stil |
| **Info** | `info2@example.com` | Müşteri kabul / randevu girişi |
| **Şoför** | `sofor2@example.com` | Transfer sorumlusu |

### Stüdyo 3 — Bağımsız Piercing Studio _(dükkan bağlantısı yok)_

| Rol | Email | Not |
|-----|-------|-----|
| **StudioAdmin** | `studioadmin1@example.com` | Studio 1 ile aynı kullanıcı |
| **Artist** | `artist1@example.com` | Studio 1 ile aynı kullanıcı |

---

## Diğer Roller

| Rol | Email | Açıklama |
|-----|-------|----------|
| **KullaniciRol** | `kullanici.rol@example.com` | Stüdyoya atanmamış kullanıcı rolü |
| **Kullanici** | `kullanici@example.com` | Temel kullanıcı |

---

## Seeded Yapı Özeti

```
Ink Empire Group (Şirket)
├── Ink Empire Kadıköy (Dükkan)  ← yonetici@example.com
│   └── Studio 1: Ink Empire Kadıköy Tattoo
│       ├── 8 çalışan atanmış
│       └── 8 randevu (confirmed × 3, pending × 2, completed × 2, cancelled × 1)
└── Ink Empire Beşiktaş (Dükkan)  ← yonetici@example.com
    └── Studio 2: Ink Empire Beşiktaş Tattoo
        ├── 9 çalışan atanmış
        └── 5 randevu (confirmed × 2, pending × 1, completed × 1, cancelled × 1)

Bağımsız Piercing Studio (Dükkan bağlantısı yok)
├── 2 çalışan atanmış
└── 2 randevu (confirmed × 1, pending × 1)
```

## Randevu Durumları

| Durum | Açıklama |
|-------|----------|
| `pending` | Onay bekliyor |
| `confirmed` | Onaylandı, transfer planlandı |
| `completed` | Tamamlandı |
| `cancelled` | İptal edildi |

## Driver / Artist Durumları

| Durum | Açıklama |
|-------|----------|
| `waiting` | Müşteri konumunda bekleniyor |
| `on_way` | Transfer yolda |
| `completed` | İşlem tamamlandı |
| `assigned` | Artist atandı, bekliyor |
| `pending` | Henüz atama yapılmadı |
