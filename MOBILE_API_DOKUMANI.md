# Randevu Mobil Uygulaması — Backend API Spesifikasyonları

Bu doküman mobil uygulamanın beklediği endpoint'leri, istek alanlarını ve dönen JSON formatlarını güncel haliyle tanımlar.

**Base URL:** `http://127.0.0.1:8000/api`

---

## Genel Kurallar

1. `POST /api/login`, `POST /api/register` ve `/api/public/*` hariç tüm endpoint'lerde `Authorization: Bearer {token}` header'ı kullanılır.
2. Tüm isteklerde `Accept: application/json` header'ı gönderilmelidir.
3. Roller aşağıda açıklanmıştır.

---

## Rol Sistemi

### Platform Rolleri (users.role)
| Rol | Değer | Açıklama |
|---|---|---|
| Admin | `admin` | Tüm sistemi yönetir |
| Yönetici | `yonetici` | Dükkanları ve stüdyoları yönetir |
| Kullanıcı (Rol) | `kullanici_rol` | Bağımsız artist / freelancer |
| Kullanıcı | `kullanici` | Temel genel kullanıcı |

### Stüdyo Rolleri (studio_user.role — pivot)
| Rol | Değer | Açıklama |
|---|---|---|
| Stüdyo Yöneticisi | `studio_admin` | Stüdyoyu tam yönetir (logo, personel, bildirim) |
| Süpervizör | `supervisor` | Tüm randevuları yönetir, artiste atar |
| Tasarımcı | `designer` | Stüdyo hareketlerini görür, randevu oluşturur |
| Artist | `artist` | Atanan dövmeleri görür, kabul/red verir |
| Info | `info` | Randevu oluşturur/düzenler, stüdyoyu görür |
| Şoför | `sofor` | Randevu oluşturur/düzenler, alım/bırakım bildirir |

### Yetki Hiyerarşisi

```
admin
 └─ yonetici (dükkan seviyesi)
     └─ studio_admin (stüdyo seviyesi)
         └─ supervisor (randevu yönetimi + artist atama)
             ├─ designer  (randevu oluşturma)
             ├─ info      (randevu oluşturma/düzenleme)
             └─ sofor     (randevu oluşturma/düzenleme + alım/bırakım)
         └─ artist        (atanan randevuları görür + kabul/red)
```

**Not:** Kayıt sırasında kullanıcı `kullanici` veya `kullanici_rol` olarak belirler. Stüdyo rolleri admin/stüdyo yöneticisi tarafından atanır.

---

## 1. Auth ve Profil

### 1.1 Kayıt

**POST** `/api/register`

> Yeni kullanıcı hesabı oluşturur. `role` belirtilmezse `kullanici` atanır.

```json
{
  "name": "Ahmet",
  "surname": "Yılmaz",
  "email": "ahmet@example.com",
  "phone": "5551234567",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "kullanici_rol",
  "bio": "10 yıllık dövme sanatçısı."
}
```

> `role`: `kullanici` (varsayılan) | `kullanici_rol` (portfolyolu bağımsız artist)

**Response `201`**

```json
{
  "message": "Kayıt başarılı.",
  "data": {
    "token": "1|xxxxxxxxx",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "Ahmet Yılmaz",
      "email": "ahmet@example.com",
      "role": "kullanici_rol"
    }
  }
}
```

---

### 1.2 Giriş

**POST** `/api/login`

```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response `200`**

```json
{
  "message": "Giriş başarılı.",
  "data": {
    "token": "1|xxxxxxxxx",
    "token_type": "Bearer",
    "studio_id": 1,
    "user": {
      "id": 1,
      "name": "Ahmet Yılmaz",
      "email": "ahmet@example.com",
      "role": "artist",
      "profile_image": "profiles/ahmet.png",
      "bio": "10 yıllık dövme sanatçısı.",
      "status": "working",
      "is_active": true
    }
  }
}
```

> `role`: Stüdyo üyesiyse pivot'taki stüdyo rolü döner. Değilse global rol döner.

---

### 1.3 Profil Getir

**GET** `/api/profile` veya **GET** `/api/me`

**Response `200`**

```json
{
  "data": {
    "id": 1,
    "name": "Ahmet Yılmaz",
    "email": "ahmet@example.com",
    "phone": "5551234567",
    "bio": "10 yıllık dövme sanatçısı.",
    "portfolio": [
      {
        "title": "Tribal Kol Dövmesi",
        "image_path": "portfolio/tribal_kol.jpg",
        "description": "Siyah-gri tribal stil."
      }
    ],
    "role": "artist",
    "profile_image": "profiles/ahmet.png",
    "rating": 4.8,
    "status": "working",
    "location": "Merkez Stüdyo",
    "is_active": true,
    "current_studio": {
      "id": 1,
      "name": "Merkez Stüdyo",
      "location": "Antalya"
    },
    "studio_history": [
      {
        "id": 2,
        "name": "Eski Stüdyo",
        "location": "İstanbul",
        "joined_at": "2024-01-15 09:00:00",
        "left_at": "2025-01-15 18:00:00"
      }
    ],
    "created_at": "2026-04-27T10:00:00+03:00"
  }
}
```

---

### 1.4 Profil Güncelle

**PATCH** `/api/profile` veya **PATCH** `/api/me`

```json
{
  "name": "Ahmet",
  "surname": "Yılmaz",
  "email": "ahmet@example.com",
  "phone": "5551234567",
  "bio": "Güncellenmiş bio.",
  "profile_image": "profiles/ahmet_yeni.png",
  "status": "break",
  "password": "654321",
  "password_confirmation": "654321"
}
```

---

### 1.5 Portfolyo Getir

**GET** `/api/me/portfolio`

**Response `200`**

```json
{
  "data": {
    "portfolio": [
      {
        "title": "Tribal Kol",
        "image_path": "portfolio/tribal.jpg",
        "description": "Siyah-gri stil."
      }
    ]
  }
}
```

---

### 1.6 Portfolyo Güncelle

**PATCH** `/api/me/portfolio`

> Artist ve `kullanici_rol` kullanıcılar portfolyo yönetebilir.

```json
{
  "portfolio": [
    {
      "title": "Tribal Kol",
      "image_path": "portfolio/tribal.jpg",
      "description": "Siyah-gri stil."
    },
    {
      "title": "Çiçek Bileği",
      "image_path": "portfolio/cicek.jpg",
      "description": null
    }
  ]
}
```

> Mevcut portfolyo tamamen yenisiyle değiştirilir. Tüm öğeleri gönder.

---

### 1.7 Çıkış

**POST** `/api/logout`

---

## 2. Herkese Açık Keşif (Auth gerektirmez)

### 2.1 Stüdyoları Listele

**GET** `/api/public/studios`

```json
{
  "data": [
    {
      "id": 1,
      "name": "Merkez Stüdyo",
      "location": "Antalya",
      "logo_path": "logos/merkez.png",
      "shop": { "name": "Merkez Dükkan" }
    }
  ]
}
```

---

### 2.2 Stüdyo Profili

**GET** `/api/public/studios/{studio_id}`

```json
{
  "data": {
    "id": 1,
    "name": "Merkez Stüdyo",
    "location": "Antalya",
    "logo_path": "logos/merkez.png",
    "shop": { "id": 1, "name": "Merkez Dükkan" },
    "artists": [
      {
        "id": 3,
        "name": "Ahmet Yılmaz",
        "profile_image": "profiles/ahmet.png",
        "bio": "10 yıllık dövme sanatçısı.",
        "rating": 4.8,
        "portfolio": []
      }
    ]
  }
}
```

---

### 2.3 Artist Listesi

**GET** `/api/public/artists`

---

### 2.4 Artist Profili

**GET** `/api/public/artists/{user_id}`

```json
{
  "data": {
    "id": 3,
    "name": "Ahmet Yılmaz",
    "profile_image": "profiles/ahmet.png",
    "bio": "10 yıllık dövme sanatçısı.",
    "rating": 4.8,
    "portfolio": [
      {
        "title": "Tribal Kol",
        "image_path": "portfolio/tribal.jpg",
        "description": "Siyah-gri stil."
      }
    ],
    "studios": [
      {
        "id": 1,
        "name": "Merkez Stüdyo",
        "location": "Antalya",
        "logo_path": null
      }
    ]
  }
}
```

---

## 3. Dashboard ve Raporlama

### 3.1 Anasayfa Özeti

**GET** `/api/home`

| Query Parametresi | Tip | Açıklama |
|---|---|---|
| `date_from` | `YYYY-MM-DD` | Başlangıç tarihi (opsiyonel) |
| `date_to` | `YYYY-MM-DD` | Bitiş tarihi (opsiyonel) |
| `studio_id` | integer | Stüdyo filtresi (opsiyonel) |

---

### 3.2 Dönemsel Rapor

**GET** `/api/reports`

| Query Parametresi | Değerler | Varsayılan |
|---|---|---|
| `period` | `daily`, `weekly`, `monthly`, `quarterly` | `monthly` |
| `studio_id` | integer | — |

---

## 4. Kullanıcı ve Stüdyo Yönetimi

### 4.1 Stüdyo Ayarları Güncelle (Stüdyo Yöneticisi+)

**PATCH** `/api/studios/{studio_id}/settings`

> Yetki: `admin`, `yonetici`, `studio_admin`

```json
{
  "name": "Merkez Stüdyo (Yeni)",
  "location": "Kemer",
  "logo_path": "logos/merkez_yeni.png",
  "notification_lead_minutes": 45
}
```

---

### 4.2 Stüdyo Kullanıcıları Listele

**GET** `/api/studios/{studio_id}/users`

> Yetki: `admin`, `yonetici`, `studio_admin`

---

### 4.3 Kullanıcı Stüdyo Rolü Güncelle / Ban

**PATCH** `/api/studios/{studio_id}/users/{user_id}`

> Yetki: `admin`, `yonetici`, `studio_admin`

```json
{
  "role": "artist",
  "is_active": false,
  "status": "break"
}
```

> `is_active: false` → kullanıcıyı banlar / stüdyodan uzaklaştırır.

---

### 4.4 Stüdyo Personel Yönetimi

Tüm personel endpoint'leri `studio_admin`, `yonetici`, `admin` tarafından kullanılabilir.

| Yöntem | Endpoint | Açıklama |
|---|---|---|
| GET | `/api/studios/{id}/supervisors` | Süpervizörleri listele |
| POST | `/api/studios/{id}/supervisors` | Süpervizör ekle |
| PATCH | `/api/studios/{id}/supervisors/{user}` | Güncelle |
| DELETE | `/api/studios/{id}/supervisors/{user}` | Pasife al |
| GET | `/api/studios/{id}/artists` | Artistleri listele |
| POST | `/api/studios/{id}/artists` | Artist ekle |
| PATCH | `/api/studios/{id}/artists/{user}` | Güncelle |
| DELETE | `/api/studios/{id}/artists/{user}` | Pasife al |
| GET | `/api/studios/{id}/designers` | Tasarımcıları listele |
| POST | `/api/studios/{id}/designers` | Tasarımcı ekle |
| GET | `/api/studios/{id}/info-staff` | Info personeli listele |
| POST | `/api/studios/{id}/info-staff` | Info personeli ekle |
| GET | `/api/studios/{id}/drivers` | Şoförleri listele |
| POST | `/api/studios/{id}/drivers` | Şoför ekle |

**Personel Ekleme İstek Gövdesi:**

```json
{
  "name": "Mehmet",
  "email": "mehmet@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

> E-posta sistemde varsa kullanıcı stüdyoya bağlanır. Yoksa yeni kullanıcı oluşturulur.

---

## 5. Randevular

### 5.1 Stüdyo Randevularını Listele

**GET** `/api/studios/{studio_id}/appointments`

> Yetki: tüm stüdyo rolleri  
> `artist` yalnızca kendine atanmış randevuları görür.  
> `sofor` yalnızca kendine atanmış randevuları görür.

**Response `200`**

```json
{
  "data": [
    {
      "id": 1,
      "customer": {
        "first_name": "John",
        "last_name": "Doe",
        "phone_country_code": "+90",
        "phone_number": "5550001122",
        "hotel_name": "Hilton",
        "room_number": "402",
        "customer_notes": "VIP Müşteri"
      },
      "pax": 2,
      "appointment_at": "2026-05-05T10:00:00+03:00",
      "status": "confirmed",
      "driver_status": null,
      "artist_status": "pending",
      "notes": "Ön kapıdan alınacak.",
      "source_image_path": "uploads/slips/slip_123.jpg",
      "assigned_driver_user_id": 4,
      "assigned_artist_user_id": 5,
      "driver": {
        "id": 4,
        "name": "Şoför Bir",
        "phone": "5559998877",
        "rating": null
      },
      "artist": {
        "id": 5,
        "name": "Ahmet Yılmaz",
        "profile_image": "profiles/ahmet.png",
        "rating": 4.8
      },
      "studio": "Merkez Stüdyo",
      "created_at": "2026-05-04T08:00:00+03:00"
    }
  ]
}
```

---

### 5.2 Randevu Detayı

**GET** `/api/studios/{studio_id}/appointments/{appointment_id}`

> Yetki: tüm stüdyo rolleri

```json
{
  "data": {
    "id": 1,
    "appointment_type": "standard",
    "full_name": "John Doe",
    "customer": { "...": "..." },
    "date": "2026-05-05",
    "time": "10:00",
    "place": "Hilton Giriş",
    "pax": 2,
    "status": "confirmed",
    "driver_status": null,
    "artist_status": "pending",
    "notes": "Ön kapıdan alınacak.",
    "is_old_customer": false,
    "driver": { "id": 4, "name": "Şoför Bir", "phone": "5559998877" },
    "artist": {
      "id": 5,
      "name": "Ahmet Yılmaz",
      "profile_image": "profiles/ahmet.png",
      "rating": 4.8
    },
    "created_by": { "id": 2, "name": "Çalışan Bir" },
    "created_at": "2026-05-04T08:00:00+03:00"
  }
}
```

---

### 5.3 Müşteri Geçmişi Kontrol

**POST** `/api/studios/{studio_id}/appointments/check-customer`

```json
{
  "customer": {
    "first_name": "John",
    "last_name": "Doe",
    "phone_country_code": "+90",
    "phone_number": "5550001122"
  }
}
```

---

### 5.4 Randevu Oluştur

**POST** `/api/studios/{studio_id}/appointments`

> Yetki: `studio_admin`, `supervisor`, `designer`, `info`, `sofor`, `calisan`

```json
{
  "customer": {
    "first_name": "John",
    "last_name": "Doe",
    "phone_country_code": "+90",
    "phone_number": "5550001122",
    "hotel_name": "Hilton",
    "room_number": "402",
    "customer_notes": "VIP müşteri."
  },
  "pax": 2,
  "appointment_at": "2026-05-05T10:00:00+03:00",
  "appointment_type": "standard",
  "notes": "Ön kapıdan alınacak.",
  "source_image_path": "uploads/slips/slip_123.jpg",
  "assigned_driver_user_id": 4
}
```

---

### 5.5 Randevu Güncelle

**PATCH** `/api/studios/{studio_id}/appointments/{appointment_id}`

> Yetki: `studio_admin`, `supervisor`, `designer`, `info`, `sofor`, `calisan`

---

### 5.6 Randevu Sil

**DELETE** `/api/studios/{studio_id}/appointments/{appointment_id}`

---

### 5.7 Randevu Destek Verisi (Dropdown)

**GET** `/api/studios/{studio_id}/appointment-support`

> Şoför ve artist listelerini döner.

```json
{
  "data": {
    "drivers": [
      { "id": 4, "name": "Şoför Bir", "phone": "5559998877" }
    ],
    "artists": [
      {
        "id": 5,
        "name": "Ahmet Yılmaz",
        "phone": "5551112233",
        "profile_image": "profiles/ahmet.png",
        "rating": 4.8
      }
    ],
    "statuses": ["pending", "confirmed", "completed", "cancelled", "rescheduled"]
  }
}
```

---

### 5.8 Artist Atama (Randevu Tamamlandıktan Sonra)

**PATCH** `/api/studios/{studio_id}/appointments/{appointment_id}/assign-artist`

> Yetki: `studio_admin`, `supervisor`, `yonetici`, `admin`  
> Stüdyonun kendi artistini veya sistemdeki `kullanici_rol` kullanıcısını (freelancer) atayabilir.

```json
{
  "assigned_artist_user_id": 5
}
```

> `null` göndererek atamayı kaldırabilirsin.

**Response `200`**

```json
{
  "message": "Artist atandı.",
  "data": {
    "id": 1,
    "assigned_artist_user_id": 5,
    "artist_status": "pending"
  }
}
```

---

### 5.9 Artist Kabul/Red

**PATCH** `/api/studios/{studio_id}/appointments/{appointment_id}/artist-response`

> Yetki: yalnızca `artist` veya `kullanici_rol` — atanmış kullanıcı  
> Randevu kendine atanmışsa kabul veya reddeder.

```json
{
  "artist_status": "accepted"
}
```

| `artist_status` | Anlamı |
|---|---|
| `accepted` | Randevuyu kabul etti |
| `rejected` | Randevuyu reddetti — supervisor yeniden atama yapabilir |

**Response `200`**

```json
{
  "message": "Randevu kabul edildi.",
  "data": {
    "id": 1,
    "artist_status": "accepted"
  }
}
```

---

### 5.10 Şoför: Kendi Randevuları

**GET** `/api/my-appointments`

> Yetki: yalnızca `sofor`  
> Stüdyodan bağımsız, şoföre atanmış tüm randevuları listeler.

```json
{
  "data": [
    {
      "id": 1,
      "studio": { "id": 1, "name": "Merkez Stüdyo" },
      "customer": {
        "first_name": "John",
        "last_name": "Doe",
        "phone_country_code": "+90",
        "phone_number": "5550001122",
        "hotel_name": "Hilton",
        "room_number": "402",
        "customer_notes": null
      },
      "place": "Hilton Giriş",
      "pax": 2,
      "appointment_at": "2026-05-05T10:00:00+03:00",
      "status": "confirmed",
      "driver_status": null,
      "notes": "Ön kapıdan alınacak.",
      "created_by": { "id": 2, "name": "Çalışan Bir" },
      "created_at": "2026-05-04T08:00:00+03:00"
    }
  ]
}
```

---

### 5.11 Şoför: Sürücü Aksiyonu

**PATCH** `/api/studios/{studio_id}/appointments/{appointment_id}/driver-action`

> Yetki: yalnızca `sofor` — kendine atanmış randevularda

```json
{
  "driver_status": "picked_up"
}
```

| `driver_status` | Anlamı | Yan Etki |
|---|---|---|
| `picked_up` | Müşteriyi aldım | Ana durum değişmez |
| `dropped_off` | Müşteriyi bıraktım | Ana `status` → `completed` |
| `cancelled` | İptal ettim | Ana `status` → `cancelled` |

---

### 5.12 Artist: Kendi Randevuları

**GET** `/api/my-artist-appointments`

> Yetki: `artist`, `kullanici_rol`  
> Stüdyodan bağımsız, artiste atanmış ve reddedilmemiş randevular.

```json
{
  "data": [
    {
      "id": 1,
      "studio": { "id": 1, "name": "Merkez Stüdyo" },
      "customer": {
        "first_name": "John",
        "last_name": "Doe",
        "phone_country_code": "+90",
        "phone_number": "5550001122",
        "hotel_name": "Hilton",
        "room_number": "402",
        "customer_notes": "VIP müşteri."
      },
      "place": "Hilton Giriş",
      "pax": 2,
      "appointment_at": "2026-05-05T10:00:00+03:00",
      "status": "confirmed",
      "artist_status": "pending",
      "notes": null,
      "created_at": "2026-05-04T08:00:00+03:00"
    }
  ]
}
```

---

## 6. OCR — Fiş Okuma

**POST** `/api/ocr/appointment-slip`

> Kimlik doğrulaması gerekmez.  
> `Content-Type: multipart/form-data`

| Alan | Tip | Zorunlu |
|---|---|---|
| `image` | file | ✓ |

---

## 7. Şirket Yönetimi (Admin Only)

| Yöntem | Endpoint | Açıklama |
|---|---|---|
| GET | `/api/companies` | Şirketleri listele |
| POST | `/api/companies` | Şirket oluştur |
| PATCH | `/api/companies/{id}` | Şirket güncelle |

---

## 8. Dükkan Yönetimi

| Yöntem | Endpoint | Yetki | Açıklama |
|---|---|---|---|
| GET | `/api/shops` | Tümü | Dükkanları listele |
| POST | `/api/shops` | Admin | Dükkan oluştur |
| PATCH | `/api/shops/{id}` | Admin, Yönetici | Dükkan güncelle |
| DELETE | `/api/shops/{id}` | Admin | Dükkan sil |

---

## Endpoint Özeti

| Yöntem | Endpoint | Yetki | Açıklama |
|---|---|---|---|
| POST | `/api/register` | — | Kullanıcı kaydı |
| POST | `/api/login` | — | Giriş yap |
| GET | `/api/public/studios` | — | Stüdyo keşfi |
| GET | `/api/public/studios/{id}` | — | Stüdyo profili (artistler dahil) |
| GET | `/api/public/artists` | — | Artist listesi |
| GET | `/api/public/artists/{id}` | — | Artist profili |
| GET/PATCH | `/api/me` veya `/api/profile` | Tümü | Profil getir/güncelle |
| GET/PATCH | `/api/me/portfolio` | Tümü | Portfolyo getir/güncelle |
| POST | `/api/logout` | Tümü | Çıkış yap |
| GET | `/api/home` | Tümü | Dashboard özeti |
| GET | `/api/reports` | Tümü | Dönemsel rapor |
| GET | `/api/studios/overview` | Admin, Yönetici | Stüdyoları listele |
| POST | `/api/studios` | Admin, Yönetici | Stüdyo oluştur |
| PATCH | `/api/studios/{id}/settings` | Admin, Yönetici, Stüdyo Yöneticisi | Stüdyo ayarları |
| DELETE | `/api/studios/{id}` | Admin, Yönetici | Stüdyo sil |
| GET | `/api/studios/{id}/users` | Admin, Yönetici, Stüdyo Yöneticisi | Personeli listele |
| PATCH | `/api/studios/{id}/users/{id}` | Admin, Yönetici, Stüdyo Yöneticisi | Personel güncelle/banla |
| GET/POST/PATCH/DELETE | `/api/studios/{id}/supervisors` | Admin, Yönetici, Stüdyo Yöneticisi | Süpervizör yönetimi |
| GET/POST/PATCH/DELETE | `/api/studios/{id}/artists` | Admin, Yönetici, Stüdyo Yöneticisi | Artist yönetimi |
| GET/POST/PATCH/DELETE | `/api/studios/{id}/designers` | Admin, Yönetici, Stüdyo Yöneticisi | Tasarımcı yönetimi |
| GET/POST/PATCH/DELETE | `/api/studios/{id}/info-staff` | Admin, Yönetici, Stüdyo Yöneticisi | Info personeli |
| GET/POST/PATCH/DELETE | `/api/studios/{id}/drivers` | Admin, Yönetici, Stüdyo Yöneticisi | Şoför yönetimi |
| GET | `/api/studios/{id}/appointments` | Tüm stüdyo rolleri | Randevu listesi |
| POST | `/api/studios/{id}/appointments` | Studio Admin, Supervisor, Designer, Info, Şoför | Randevu oluştur |
| GET | `/api/studios/{id}/appointments/{id}` | Tüm stüdyo rolleri | Randevu detayı |
| PATCH | `/api/studios/{id}/appointments/{id}` | Studio Admin, Supervisor, Designer, Info, Şoför | Randevu güncelle |
| DELETE | `/api/studios/{id}/appointments/{id}` | Studio Admin, Supervisor, Designer, Info, Şoför | Randevu sil |
| PATCH | `/api/studios/{id}/appointments/{id}/assign-artist` | Studio Admin, Supervisor+ | Artist ata |
| PATCH | `/api/studios/{id}/appointments/{id}/artist-response` | **Artist** | Kabul / Red |
| GET | `/api/my-artist-appointments` | **Artist, Kullanıcı (Rol)** | Atanmış randevularım |
| PATCH | `/api/studios/{id}/appointments/{id}/driver-action` | **Şoför** | Alım/bırakım/iptal |
| GET | `/api/my-appointments` | **Şoför** | Atanmış tüm randevularım |
| POST | `/api/studios/{id}/appointments/check-customer` | Stüdyo çalışanları | Müşteri geçmişi |
| GET | `/api/studios/{id}/appointment-support` | Stüdyo çalışanları | Dropdown verisi |
| POST | `/api/ocr/appointment-slip` | — | Fiş OCR |

---

## Test Hesapları

| E-posta | Şifre | Rol |
|---|---|---|
| admin@example.com | 123456 | Admin |
| manager@example.com | 123456 | Yönetici |
| studio-admin@example.com | 123456 | Stüdyo Yöneticisi |
| supervisor@example.com | 123456 | Süpervizör |
| artist@example.com | 123456 | Artist |
| designer@example.com | 123456 | Tasarımcı |
| info@example.com | 123456 | Info |
| driver@example.com | 123456 | Şoför |
| user-rol@example.com | 123456 | Kullanıcı (Rol) |
| user@example.com | 123456 | Kullanıcı |
