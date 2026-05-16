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
| Süpervizör | `supervisor` | Stüdyoyu tam yönetir (logo, personel, bildirim, randevu, artist atama) |
| Tasarımcı | `designer` | Tasarım randevusu oluşturur/düzenler/görür |
| Artist | `artist` | Atanan dövme randevularını görür, kabul/red verir |
| Info | `info` | Randevu oluşturur/düzenler, stüdyoyu görür |
| Şoför | `sofor` | Şubesinin tüm randevularını görür, alım/bırakım günceller |

### Yetki Hiyerarşisi

```
admin
 └─ yonetici (dükkan seviyesi)
     └─ supervisor (stüdyo yöneticisi — randevu, personel, artist atama, logo)
         ├─ designer  (randevu oluşturma/düzenleme)
         ├─ info      (randevu oluşturma/düzenleme)
         └─ sofor     (randevu oluşturma/düzenleme + alım/bırakım)
     └─ artist        (atanan randevuları görür + kabul/red)
```

**Not:** Kayıt sırasında kullanıcı `kullanici` veya `kullanici_rol` olarak belirler. Stüdyo rolleri admin/yönetici/süpervizör tarafından atanır.

---

## Test Kullanıcıları

> Tüm şifreler: `123456`

### Platform Yöneticileri
| Email | Rol | Stüdyo Erişimi |
|---|---|---|
| `admin@example.com` | `admin` | Tümü |
| `yonetici@example.com` | `yonetici` | Ink Empire Kadıköy + Beşiktaş |

### Stüdyo 1 — Ink Empire Kadıköy Tattoo (ID: 1)
| Email | Rol |
|---|---|
| `supervisor1@example.com` | `supervisor` (stüdyo yöneticisi) |
| `designer1@example.com` | `designer` |
| `artist1@example.com` | `artist` |
| `artist1b@example.com` | `artist` |
| `info1@example.com` | `info` |
| `sofor1@example.com` | `sofor` |
| `calisan@example.com` | `calisan` |

### Stüdyo 2 — Ink Empire Beşiktaş Tattoo (ID: 2)
| Email | Rol |
|---|---|
| `supervisor2@example.com` | `supervisor` (stüdyo yöneticisi) |
| `designer2@example.com` | `designer` |
| `artist2@example.com` | `artist` |
| `artist2b@example.com` | `artist` |
| `info2@example.com` | `info` |
| `sofor2@example.com` | `sofor` |

### Stüdyo 3 — Bağımsız Piercing Studio (ID: 3)
| Email | Rol |
|---|---|
| `supervisor1@example.com` | `supervisor` *(Studio 1 ile aynı kullanıcı, studio sahibi)* |
| `artist1@example.com` | `artist` *(Studio 1 ile aynı kullanıcı)* |

### Bağımsız Kullanıcılar
| Email | Rol | Not |
|---|---|---|
| `freelancer@example.com` | `kullanici_rol` | Portfolyosu var, stüdyosuz |
| `kullanici@example.com` | `kullanici` | Sıradan uygulama kullanıcısı |

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
  "status": "break",
  "password": "654321",
  "password_confirmation": "654321"
}
```

> `profile_image` alanını bu endpoint ile ayarlama. Profil fotoğrafı için 1.5'i kullan.

---

### 1.5 Profil Fotoğrafı Yükle

**POST** `/api/me/avatar`

> `Content-Type: multipart/form-data`

| Alan | Tip | Zorunlu |
|---|---|---|
| `avatar` | image (jpeg/png/jpg/webp, max 5 MB) | Evet |

**Response `200`**

```json
{
  "status": "success",
  "message": "Profil fotoğrafı güncellendi.",
  "profile_image": "http://server/storage/avatars/1/uuid.jpg"
}
```

> Dönen `profile_image` URL'i, sonraki `/api/me` yanıtında da aynı şekilde gelir.

---

### 1.6 Portfolyo Getir

**GET** `/api/me/portfolio`

**Response `200`**

```json
{
  "data": {
    "portfolio": [
      {
        "title": "Tribal Kol",
        "image_path": "http://server/storage/portfolio/1/uuid.jpg",
        "description": "Siyah-gri stil.",
        "category": "tribal"
      }
    ]
  }
}
```

---

### 1.7 Portfolyoya Öğe Ekle (Önerilen)

**POST** `/api/me/portfolio/items`

> Tek adımda görsel + bilgi gönder. `Content-Type: multipart/form-data`

| Alan | Tip | Zorunlu |
|---|---|---|
| `title` | string | Evet |
| `image` | image (jpeg/png/jpg/webp, max 10 MB) | Hayır |
| `image_path` | string (görece path veya tam URL) | Hayır |
| `description` | string | Hayır |
| `category` | string | Hayır |

> `image` (dosya) ve `image_path` birlikte gönderilirse `image` önceliklidir.

**Response `200`**

```json
{
  "status": "success",
  "message": "Portfolyo öğesi eklendi.",
  "data": {
    "portfolio": [
      {
        "title": "Tribal Kol",
        "image_path": "http://server/storage/portfolio/1/uuid.jpg",
        "description": "Siyah-gri stil."
      }
    ]
  }
}
```

---

### 1.8 Portfolyodan Öğe Sil

**DELETE** `/api/me/portfolio/items/{index}`

> `{index}`: portfolyo dizisindeki 0 tabanlı sıra numarası.

**Response `200`**

```json
{
  "status": "success",
  "message": "Portfolyo öğesi silindi.",
  "data": { "portfolio": [] }
}
```

---

### 1.9 Portfolyo Görsel Yükle (Ayrı adım)

**POST** `/api/me/portfolio/upload`

> Sadece görseli yükler, portfolyoya kaydetmez. Dönen `image_path`'i 1.7'deki öğe ekleme endpoint'ine gönder.

> `Content-Type: multipart/form-data`

| Alan | Tip | Zorunlu |
|---|---|---|
| `image` | image (jpeg/png/jpg/webp, max 10 MB) | Evet |

**Response `200`**

```json
{
  "status": "success",
  "message": "Görsel yüklendi.",
  "image_path": "portfolio/1/uuid.jpg",
  "image_url": "http://server/storage/portfolio/1/uuid.jpg"
}
```

> `image_path` → `POST /me/portfolio/items` isteğinde `image_path` alanına gönder.  
> `image_url` → Görseli hemen göstermek için kullan.

---

### 1.10 Portfolyo Tamamen Değiştir

**PATCH** `/api/me/portfolio`

> Tüm portfolyoyu gönder; mevcut portfolyo yenisiyle tamamen değiştirilir.

```json
{
  "portfolio": [
    {
      "title": "Tribal Kol",
      "image_path": "portfolio/1/uuid.jpg",
      "description": "Siyah-gri stil.",
      "category": "tribal"
    }
  ]
}
```

---

### 1.11 Çıkış

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

> Yetki: `admin`, `yonetici`, `supervisor`

```json
{
  "name": "Merkez Stüdyo (Yeni)",
  "location": "Kemer",
  "logo_path": "logos/merkez_yeni.png"
}
```

---

### 4.2 Stüdyo Kullanıcıları Listele

**GET** `/api/studios/{studio_id}/users`

> Yetki: `admin`, `yonetici`, `supervisor`

---

### 4.3 Kullanıcı Stüdyo Rolü Güncelle / Ban

**PATCH** `/api/studios/{studio_id}/users/{user_id}`

> Yetki: `admin`, `yonetici`, `supervisor`

```json
{
  "role": "designer",
  "is_active": false,
  "status": "break"
}
```

> `is_active: false` → kullanıcıyı banlar / stüdyodan uzaklaştırır.

**Geçerli `role` değerleri:**

| Değer | Açıklama | Atayabilir |
|---|---|---|
| `supervisor` | Stüdyo yöneticisi | Admin, Yönetici |
| `designer` | Tasarımcı | Admin, Yönetici, Supervisor |
| `artist` | Artist | Admin, Yönetici, Supervisor |
| `info` | Info çalışanı | Admin, Yönetici, Supervisor |
| `sofor` | Şoför | Admin, Yönetici, Supervisor |
| `calisan` | Genel çalışan | Admin, Yönetici, Supervisor |

> Supervisor kendi seviyesinde veya üstünde rol (`admin`, `yonetici`, `supervisor`) atayamaz.

---

### 4.4 Stüdyo Personel Yönetimi

Tüm personel endpoint'leri `supervisor`, `yonetici`, `admin` tarafından kullanılabilir.

| Yöntem | Endpoint | Açıklama |
|---|---|---|
| GET | `/api/studios/{id}/supervisors` | Süpervizörleri listele |
| POST | `/api/studios/{id}/supervisors` | Süpervizör ekle/ata |
| PATCH | `/api/studios/{id}/supervisors/{user}` | Güncelle |
| DELETE | `/api/studios/{id}/supervisors/{user}` | Pasife al |
| GET | `/api/studios/{id}/artists` | Artistleri listele |
| POST | `/api/studios/{id}/artists` | Artist ekle/ata |
| PATCH | `/api/studios/{id}/artists/{user}` | Güncelle |
| DELETE | `/api/studios/{id}/artists/{user}` | Pasife al |
| GET | `/api/studios/{id}/designers` | Tasarımcıları listele |
| POST | `/api/studios/{id}/designers` | Tasarımcı ekle/ata |
| PATCH | `/api/studios/{id}/designers/{user}` | Güncelle |
| DELETE | `/api/studios/{id}/designers/{user}` | Pasife al |
| GET | `/api/studios/{id}/info-staff` | Info personeli listele |
| POST | `/api/studios/{id}/info-staff` | Info personeli ekle/ata |
| PATCH | `/api/studios/{id}/info-staff/{user}` | Güncelle |
| DELETE | `/api/studios/{id}/info-staff/{user}` | Pasife al |
| GET | `/api/studios/{id}/drivers` | Şoförleri listele |
| POST | `/api/studios/{id}/drivers` | Şoför ekle/ata |
| PATCH | `/api/studios/{id}/drivers/{user}` | Güncelle |
| DELETE | `/api/studios/{id}/drivers/{user}` | Pasife al |

**Personel Ekleme İstek Gövdesi:**

```json
{
  "name": "Mehmet",
  "surname": "Kaya",
  "phone": "5551234567",
  "email": "mehmet@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

> - `password` **opsiyoneldir.** E-posta sistemde kayıtlıysa mevcut kullanıcı stüdyoya bağlanır; şifre girilmişse güncellenir.  
> - E-posta sistemde yoksa yeni kullanıcı oluşturulur — bu durumda `password` zorunludur.

### 4.5 Genel Kullanıcı Oluştur / Ata

**POST** `/api/users`

> Yetki: `admin`, `yonetici`, `supervisor`  
> Rol ve stüdyo belirterek personel oluşturur veya mevcut kullanıcıyı stüdyoya atar.  
> 4.4'teki rol bazlı endpoint'lere alternatif tek endpoint.

```json
{
  "name": "Ali",
  "surname": "Demir",
  "phone": "5559998877",
  "email": "ali@example.com",
  "role": "designer",
  "studio_id": 1,
  "password": "password123",
  "password_confirmation": "password123"
}
```

> `role`: `supervisor` | `designer` | `artist` | `info` | `sofor` | `calisan`  
> `supervisor` rolü yalnızca `admin` ve `yonetici` atayabilir.

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

> Yetki: `supervisor`, `supervisor`, `designer`, `info`, `sofor`, `calisan`

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

> Yetki: `supervisor`, `supervisor`, `designer`, `info`, `sofor`, `calisan`

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

> Yetki: `supervisor`, `supervisor`, `yonetici`, `admin`  
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
| PATCH | `/api/studios/{id}/settings` | Admin, Yönetici, **Supervisor** | Stüdyo ayarları |
| DELETE | `/api/studios/{id}` | Admin, Yönetici | Stüdyo sil |
| GET | `/api/studios/{id}/users` | Admin, Yönetici, **Supervisor** | Personeli listele |
| PATCH | `/api/studios/{id}/users/{id}` | Admin, Yönetici, **Supervisor** | Personel güncelle/banla |
| POST | `/api/users` | Admin, Yönetici, **Supervisor** | Kullanıcı oluştur/ata |
| GET/POST/PATCH/DELETE | `/api/studios/{id}/supervisors` | Admin, Yönetici, Supervisor | Süpervizör yönetimi |
| GET/POST/PATCH/DELETE | `/api/studios/{id}/artists` | Admin, Yönetici, Supervisor | Artist yönetimi |
| GET/POST/PATCH/DELETE | `/api/studios/{id}/designers` | Admin, Yönetici, Supervisor | Tasarımcı yönetimi |
| GET/POST/PATCH/DELETE | `/api/studios/{id}/info-staff` | Admin, Yönetici, Supervisor | Info personeli |
| GET/POST/PATCH/DELETE | `/api/studios/{id}/drivers` | Admin, Yönetici, Supervisor | Şoför yönetimi |
| GET | `/api/studios/{id}/appointments` | Tüm stüdyo rolleri | Randevu listesi |
| POST | `/api/studios/{id}/appointments` | Supervisor, Designer, Info, Şoför, Calisan | Randevu oluştur |
| GET | `/api/studios/{id}/appointments/{id}` | Tüm stüdyo rolleri | Randevu detayı |
| PATCH | `/api/studios/{id}/appointments/{id}` | Supervisor, Designer, Info, Şoför, Calisan | Randevu güncelle |
| DELETE | `/api/studios/{id}/appointments/{id}` | Supervisor, Designer, Info, Şoför, Calisan | Randevu sil |
| PATCH | `/api/studios/{id}/appointments/{id}/assign-artist` | Admin, Yönetici, **Supervisor** | Artist ata |
| PATCH | `/api/studios/{id}/appointments/{id}/artist-response` | **Artist** | Kabul / Red |
| GET | `/api/my-artist-appointments` | **Artist, Kullanıcı (Rol)** | Atanmış randevularım |
| PATCH | `/api/studios/{id}/appointments/{id}/driver-action` | **Şoför** | Alım/bırakım/iptal |
| GET | `/api/my-appointments` | **Şoför** | Atanmış tüm randevularım |
| POST | `/api/studios/{id}/appointments/check-customer` | Stüdyo çalışanları | Müşteri geçmişi |
| GET | `/api/studios/{id}/appointment-support` | Stüdyo çalışanları | Dropdown verisi |
| POST | `/api/ocr/appointment-slip` | — | Fiş OCR |

---

## Test Hesapları

> **Tüm hesaplarda şifre:** `123456`  
> Seeder: `php artisan migrate:fresh --seed`

### Platform Yöneticileri

| E-posta | Şifre | Rol |
|---|---|---|
| admin@example.com | 123456 | admin |
| yonetici@example.com | 123456 | yonetici |

### Stüdyo 1 — Ink Empire Kadıköy Tattoo (studio_id: 1)

| E-posta | Şifre | Stüdyo Rolü |
|---|---|---|
| supervisor1@example.com | 123456 | supervisor |
| designer1@example.com | 123456 | designer |
| artist1@example.com | 123456 | artist |
| artist1b@example.com | 123456 | artist |
| info1@example.com | 123456 | info |
| sofor1@example.com | 123456 | sofor |
| calisan@example.com | 123456 | calisan |

### Stüdyo 2 — Ink Empire Beşiktaş Tattoo (studio_id: 2)

| E-posta | Şifre | Stüdyo Rolü |
|---|---|---|
| supervisor2@example.com | 123456 | supervisor |
| designer2@example.com | 123456 | designer |
| artist2@example.com | 123456 | artist |
| artist2b@example.com | 123456 | artist |
| info2@example.com | 123456 | info |
| sofor2@example.com | 123456 | sofor |

### Stüdyo 3 — Bağımsız Piercing Studio (studio_id: 3, dükkan bağlantısı yok)

| E-posta | Şifre | Stüdyo Rolü |
|---|---|---|
| supervisor1@example.com | 123456 | supervisor (studio sahibi, Studio 1 ile aynı kullanıcı) |
| artist1@example.com | 123456 | artist (Studio 1 ile aynı kullanıcı) |

### Bağımsız Kullanıcılar (Stüdyoya Atanmamış)

| E-posta | Şifre | Platform Rolü |
|---|---|---|
| freelancer@example.com | 123456 | kullanici_rol |
| kullanici@example.com | 123456 | kullanici |

---

## Değişiklik Geçmişi

### 2026-05-08
- **4.3** `role` alanı genişletildi: `designer`, `artist`, `info`, `supervisor` artık geçerli değerler. Stüdyo Yöneticisi kendi seviyesinde veya üstünde rol atayamaz.
- **4.4** Personel ekleme body'sine `surname` ve `phone` alanları eklendi. `password` artık **opsiyonel** — mevcut e-posta varsa kullanıcı atanır, yoksa yeni kullanıcı oluşturulur.
- **4.4** `designers` ve `info-staff` için eksik PATCH/DELETE endpoint'leri tabloya eklendi.
- **4.5** `POST /api/users` endpoint'i eklendi: `supervisor` da bu endpoint ile kullanıcı oluşturabilir/atayabilir.
- **Yetki** `supervisor` rolü artık `POST /api/users` yapabilir (önceki kısıtlama: yalnızca `admin` ve `yonetici`).

### 2026-05-11 (Güncelleme 1)
- **Portfolio & Profil sistemi** — Şirket, şube ve stüdyo için portfolio/profil endpoint'leri eklendi.
- **Çalışma saatleri** — `shops` ve `studios` tablosuna `opening_time` / `closing_time` alanları eklendi (format: `HH:mm`).
- **Galeri görselleri** — `companies`, `shops`, `studios` tablosuna `gallery_images` (JSON dizi) ve `about` alanları eklendi.
- **Logo yükleme** — Şirket, şube ve stüdyo için `multipart/form-data` ile gerçek dosya yükleme desteği.
- **Şube logosu** — `shops` tablosuna `logo_path` eklendi.
- **Şirket** `about` ve `website` alanları eklendi.

### 2026-05-11 (Güncelleme 2)
- **Randevu türleri** — `appointment_type` artık serbest metin değil, sabit enum: `standard` (Standart), `designer` (Tasarımcı Randevusu), `tattoo` (Dövme Randevusu).
- **Tüm stüdyo çalışanları tüm randevuları görür** — `GET /api/studios/{studio}/appointments` artık rol bazlı filtreleme yapmaz; tüm stüdyo üyeleri tüm randevuları listeler.

#### Randevu Türleri (appointment_type)
| Değer | Açıklama |
|-------|----------|
| `standard` | Standart randevu (varsayılan) |
| `designer` | Tasarımcı randevusu |
| `tattoo` | Dövme randevusu |

---

## Profil & Portfolio API'leri

### Stüdyo Profili
`GET /api/studios/{studio}/profile` — `admin`, `yonetici`, `supervisor`

Dönen alanlar: `id`, `name`, `slug`, `location`, `about`, `logo_path`, `opening_time`, `closing_time`, `gallery_images`, bağlı şube bilgisi, randevu istatistikleri.

```json
{
  "data": {
    "id": 1,
    "name": "Stüdyo A",
    "about": "Açıklama...",
    "logo_path": "http://domain/storage/logos/studios/uuid.jpg",
    "opening_time": "09:00",
    "closing_time": "21:00",
    "gallery_images": ["http://...", "http://..."],
    "shop": {
      "id": 1, "name": "1. Şube",
      "logo_path": "...", "opening_time": "09:00", "closing_time": "22:00",
      "company": { "id": 1, "name": "Şirket A", "logo_path": "..." }
    },
    "appointment_stats": {
      "open": 12, "cancelled": 3, "completed": 87, "total": 102
    }
  }
}
```

### Şube Profili
`GET /api/shops/{shop}/profile` — `admin`, `yonetici`

Dönen alanlar: şube bilgisi, bağlı stüdyolar (her birinin portfolyosu dahil), toplu galeri (`aggregated_gallery`), toplu randevu istatistikleri.

### Şirket Profili
`GET /api/companies/{company}/profile` — yalnızca `admin`

Dönen alanlar: şirket bilgisi, tüm şubeler → stüdyolar, toplu galeri (şirket + şube + stüdyo görselleri), toplu randevu istatistikleri.

---

## Logo Yükleme API'leri

> Tüm yüklemeler `multipart/form-data` ile yapılır. Desteklenen formatlar: `jpeg`, `png`, `jpg`, `webp`. Maks. boyut: 5 MB.

| Method | Endpoint | Yetki | Alan |
|--------|----------|-------|------|
| POST | `/api/companies/{company}/logo` | admin | `logo` |
| POST | `/api/shops/{shop}/logo` | admin, yonetici | `logo` |
| POST | `/api/studios/{studio}/logo` | admin, yonetici, supervisor | `logo` |

Yanıt: `{ "logo_path": "http://..." }`

---

## Galeri Görseli API'leri

> `multipart/form-data`. Maks. boyut: 10 MB.

| Method | Endpoint | Yetki | Alan |
|--------|----------|-------|------|
| POST | `/api/companies/{company}/gallery` | admin | `image` |
| DELETE | `/api/companies/{company}/gallery` | admin | `url` (string) |
| POST | `/api/shops/{shop}/gallery` | admin, yonetici | `image` |
| DELETE | `/api/shops/{shop}/gallery` | admin, yonetici | `url` (string) |
| POST | `/api/studios/{studio}/gallery` | admin, yonetici, supervisor | `image` |
| DELETE | `/api/studios/{studio}/gallery` | admin, yonetici, supervisor | `url` (string) |

Yanıt (POST): `{ "gallery_images": ["http://...", "http://..."] }`

---

## Çalışma Saati & Portfolio Güncelleme

Mevcut `PATCH` endpoint'lerine yeni alanlar eklendi:

**`PATCH /api/shops/{shop}`** — yeni alanlar:
- `about` (string, nullable)
- `opening_time` (string `HH:mm`, nullable)
- `closing_time` (string `HH:mm`, nullable)

**`PATCH /api/studios/{studio}`** — yeni alanlar:
- `about` (string, nullable)
- `opening_time` (string `HH:mm`, nullable)
- `closing_time` (string `HH:mm`, nullable)

**`PATCH /api/companies/{company}`** — yeni alanlar:
- `about` (string, nullable)
- `website` (url string, nullable)

### 2026-05-11 (Güncelleme 3)
- **Kayıt** — `phone` artık **zorunlu** alan. `name` opsiyonel hale getirildi. Kayıt yalnızca `kullanici` rolü oluşturur.
- **Portfolio tüm rollerde** — Portfolio API'leri artık tüm authenticated kullanıcılara açık. `has_portfolio: false` dönenler (şoför, normal kullanıcı) için UI göstermez.
- **Portfolio öğe yönetimi** — Tek öğe ekle/sil endpoint'leri eklendi.
- **Avatar yükleme** — `POST /api/me/avatar` ile profil fotoğrafı yüklenebilir.
- **Portfolio görsel yükleme** — `POST /api/me/portfolio/upload` ile görsel yükle, URL döner.
- **Kullanıcı profil görüntüleme** — `GET /api/users/{user}` ile herhangi bir kullanıcının profili görüntülenebilir.

---

## Kullanıcı Kayıt (Güncel)

`POST /api/register` — kimlik doğrulaması gerekmez

| Alan | Zorunlu | Açıklama |
|------|---------|----------|
| `phone` | ✓ | Telefon numarası |
| `email` | ✓ | E-posta (unique) |
| `password` | ✓ | Min 6 karakter |
| `password_confirmation` | ✓ | Şifre tekrarı |
| `name` | — | Ad (opsiyonel) |
| `surname` | — | Soyad (opsiyonel) |
| `bio` | — | Kısa biyografi |

Yanıt: token + kullanıcı bilgileri. Oluşturulan hesap `kullanici` rolüyle açılır.

---

## Portfolio API'leri (Tüm Roller)

> Tüm endpoint'ler `Bearer token` gerektirir. Şoför ve normal kullanıcı rolleri teknik olarak erişebilir ancak `has_portfolio: false` döner — UI bu durumda portfolio bölümünü göstermez.

| Method | Endpoint | Açıklama |
|--------|----------|----------|
| GET | `/api/me/portfolio` | Kendi portfolyosu |
| PATCH | `/api/me/portfolio` | Tüm portfolyoyu değiştir |
| POST | `/api/me/portfolio/items` | Tek öğe ekle |
| DELETE | `/api/me/portfolio/items/{index}` | İndex'e göre öğe sil (0-tabanlı) |
| POST | `/api/me/portfolio/upload` | Görsel yükle → URL döner |

### Portfolio Öğesi Formatı
```json
{
  "title": "İş adı",
  "image_path": "http://domain/storage/portfolio/1/uuid.jpg",
  "description": "Açıklama (opsiyonel)",
  "category": "tattoo | design | photo | ... (opsiyonel)"
}
```

### PATCH /me/portfolio
```json
{ "portfolio": [ { "title": "...", "image_path": "...", "description": "..." }, ... ] }
```

### POST /me/portfolio/items
```json
{ "title": "Yeni İş", "image_path": "...", "category": "tattoo" }
```

---

## Avatar Yükleme

`POST /api/me/avatar` — `multipart/form-data`

| Alan | Zorunlu | Açıklama |
|------|---------|----------|
| `avatar` | ✓ | Görsel dosyası (jpeg/png/jpg/webp, max 5 MB) |

Yanıt: `{ "profile_image": "http://..." }`

---

## Kullanıcı Profili Görüntüleme

`GET /api/users/{user}` — giriş yapılmış kullanıcılar

Dönen alanlar: `id`, `name`, `bio`, `profile_image`, `rating`, `role`, `has_portfolio`, `portfolio` (null eğer şoför/normal kullanıcı), `current_studios`, `past_studios`, `member_since`.

```json
{
  "data": {
    "id": 5,
    "name": "Ali Yılmaz",
    "bio": "10 yıllık tattoo sanatçısı",
    "profile_image": "http://...",
    "rating": 4.8,
    "role": "artist",
    "has_portfolio": true,
    "portfolio": [
      { "title": "Dragon Tattoo", "image_path": "http://...", "category": "tattoo" }
    ],
    "current_studios": [
      { "id": 1, "name": "Studio A", "role": "artist" }
    ],
    "past_studios": [
      { "id": 2, "name": "Studio B", "joined_at": "2023-01-15", "left_at": "2024-06-01" }
    ],
    "member_since": "2022-08-20T10:00:00Z"
  }
}
```

> Not: Kullanıcı bir stüdyodan ayrılsa bile portfolyosu `past_studios` geçmişiyle birlikte görüntülenebilir.

### 2026-05-11 (Güncelleme 4)
- **Normal kullanıcı ana sayfası** — `GET /api/home` artık `kullanici` rolü için stüdyo keşif listesi döndürür (`type: "discovery"`).
- **Stüdyo listesi zenginleştirildi** — `GET /api/public/studios` artık `opening_time`, `closing_time`, `about`, şube ve şirket bilgilerini içeriyor.
- **Stüdyo detayı zenginleştirildi** — `GET /api/public/studios/{studio}` artık `gallery_images` (portfolio), açılış/kapanış saati, şirket bilgisi ve tüm aktif çalışanları (artist + dövmeci + tasarımcı) döndürüyor.

---

## Normal Kullanıcı Ana Sayfası

`GET /api/home` — `kullanici` rolü için otomatik olarak stüdyo listesi döner

```json
{
  "status": "success",
  "type": "discovery",
  "data": {
    "studios": [
      {
        "id": 1,
        "name": "Studio A",
        "slug": "studio-a-xyz",
        "location": "Kadıköy, İstanbul",
        "about": "10 yıldır hizmet veriyoruz...",
        "logo_path": "http://.../storage/logos/studios/uuid.jpg",
        "opening_time": "09:00",
        "closing_time": "21:00",
        "gallery_images": ["http://...", "http://..."],
        "shop": {
          "id": 2,
          "name": "1. Şube",
          "location": "Kadıköy",
          "logo_path": "http://...",
          "opening_time": "09:00",
          "closing_time": "22:00",
          "company": {
            "id": 1,
            "name": "ABC Şirketi",
            "logo_path": "http://..."
          }
        }
      }
    ]
  }
}
```

> Not: `opening_time` / `closing_time` önce stüdyo'dan, yoksa bağlı şubeden alınır.

---

## Stüdyo Detay (Public)

`GET /api/public/studios/{studio}` — kimlik doğrulaması gerekmez

Yeni alanlar: `about`, `opening_time`, `closing_time`, `gallery_images`, `shop.company`, `staff` (artist + dövmeci + tasarımcı rolleri, portfolyolarıyla birlikte).

```json
{
  "data": {
    "gallery_images": ["http://...", "http://..."],
    "staff": [
      {
        "id": 5, "name": "Ali Yılmaz", "role": "artist",
        "profile_image": "http://...", "bio": "...", "rating": 4.8,
        "portfolio": [{ "title": "Dragon Tattoo", "image_path": "http://..." }]
      }
    ]
  }
}
```
