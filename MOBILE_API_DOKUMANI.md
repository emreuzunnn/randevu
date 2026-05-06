# Randevu Mobil Uygulaması — Backend API Spesifikasyonları

Bu doküman mobil uygulamanın beklediği endpoint'leri, istek alanlarını ve dönen JSON formatlarını güncel haliyle tanımlar.

**Base URL:** `http://127.0.0.1:8000/api`

---

## Genel Kurallar

1. `POST /api/login` hariç tüm endpoint'lerde `Authorization: Bearer {token}` header'ı kullanılır.
2. Tüm isteklerde `Accept: application/json` header'ı gönderilmelidir.
3. Roller: `admin`, `yonetici`, `supervisor`, `calisan`, `sofor`
4. Kullanıcı durumları: `working`, `break`, `transfer`
5. Randevu durumları: `pending`, `confirmed`, `completed`, `cancelled`, `rescheduled`

---

## Yetki Hiyerarşisi

| Rol | Yetki |
|---|---|
| `admin` | Tüm dükkanları, stüdyoları, kullanıcıları ve randevuları yönetir |
| `yonetici` | Kendine bağlı dükkanları, stüdyoları, kullanıcıları ve randevuları yönetir |
| `supervisor` | Yalnızca randevu tarafını yönetir; kullanıcı/stüdyo ayarı değiştiremez |
| `calisan` | Randevu oluşturabilir ve güncelleyebilir |
| `sofor` | Randevu akışını takip eder |

Yapı: **şirket → dükkan → stüdyo → personel / randevu**

---

## 1. Auth ve Profil

### 1.1 Giriş

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
      "role": "calisan",
      "profile_image": "profiles/ahmet.png",
      "status": "working",
      "is_active": true
    }
  }
}
```

**Response `422`** — hatalı kimlik bilgisi

```json
{
  "message": "Email veya şifre hatalı."
}
```

---

### 1.2 Profil Getir

**GET** `/api/profile` veya **GET** `/api/me`

**Response `200`**

```json
{
  "data": {
    "id": 1,
    "name": "Ahmet Yılmaz",
    "email": "ahmet@example.com",
    "phone": "5551234567",
    "role": "calisan",
    "profile_image": "profiles/ahmet.png",
    "status": "working",
    "location": "Merkez Stüdyo",
    "is_active": true,
    "created_at": "2026-04-27T10:00:00+03:00"
  }
}
```

> `location`: kullanıcının birincil stüdyosunun veya yönettiği dükkanın lokasyonudur.

---

### 1.3 Profil Güncelle

**PATCH** `/api/profile` veya **PATCH** `/api/me`

Tüm alanlar opsiyoneldir; yalnızca değişen alanlar gönderilir.

```json
{
  "name": "Ahmet",
  "surname": "Yılmaz",
  "email": "ahmet@example.com",
  "phone": "5551234567",
  "profile_image": "profiles/ahmet.png",
  "status": "break",
  "password": "654321",
  "password_confirmation": "654321"
}
```

> `status` yalnızca `working`, `break`, `transfer` değerlerini kabul eder.  
> Şifre güncellenecekse `password_confirmation` ile birlikte gönderilmelidir.

**Response `200`**

```json
{
  "message": "Profil güncellendi.",
  "data": {
    "id": 1,
    "name": "Ahmet Yılmaz",
    "email": "ahmet@example.com",
    "phone": "5551234567",
    "role": "calisan",
    "profile_image": "profiles/ahmet.png",
    "status": "break",
    "location": "Merkez Stüdyo",
    "is_active": true,
    "created_at": "2026-04-27T10:00:00+03:00"
  }
}
```

---

### 1.4 Çıkış

**POST** `/api/logout`

**Response `200`**

```json
{
  "message": "Çıkış yapıldı."
}
```

---

## 2. Dashboard ve Raporlama

### 2.1 Anasayfa Özeti

**GET** `/api/home`

| Query Parametresi | Tip | Açıklama |
|---|---|---|
| `date_from` | `YYYY-MM-DD` | Başlangıç tarihi (opsiyonel) |
| `date_to` | `YYYY-MM-DD` | Bitiş tarihi (opsiyonel) |
| `studio_id` | integer | Stüdyo filtresi (opsiyonel) |

> `admin` tüm stüdyoların verisini görür. `yonetici` yalnızca kendi dükkanına bağlı stüdyoları görür.

**Response `200`**

```json
{
  "data": {
    "summary": {
      "total_appointments": 12,
      "cancelled_appointments": 2,
      "active_staff_count": 5,
      "transfer_count": 7
    },
    "reports": {
      "daily": {
        "label": "Günlük",
        "date_from": "2026-05-05",
        "date_to": "2026-05-05",
        "total_appointments": 5,
        "completed_appointments": 2,
        "cancelled_appointments": 1,
        "confirmed_appointments": 1,
        "pending_appointments": 1
      },
      "monthly": {
        "label": "Aylık",
        "date_from": "2026-05-01",
        "date_to": "2026-05-31",
        "total_appointments": 40,
        "completed_appointments": 21,
        "cancelled_appointments": 6,
        "confirmed_appointments": 8,
        "pending_appointments": 5
      },
      "quarterly": {
        "label": "3 Aylık",
        "date_from": "2026-03-01",
        "date_to": "2026-05-31",
        "total_appointments": 120,
        "completed_appointments": 71,
        "cancelled_appointments": 18,
        "confirmed_appointments": 17,
        "pending_appointments": 14
      }
    },
    "studios": [
      {
        "id": 1,
        "name": "Merkez Stüdyo",
        "location": "Antalya",
        "total_staff_count": 8,
        "active_staff_count": 3,
        "appointments_count": 22
      }
    ],
    "today_appointments": [
      {
        "id": 1,
        "customer": {
          "first_name": "Fabian",
          "last_name": "Uzun",
          "hotel_name": "Ramada"
        },
        "pax": 3,
        "appointment_at": "2026-05-05T17:30:00+03:00",
        "status": "pending",
        "studio": "Merkez Stüdyo",
        "driver": {
          "id": 4,
          "name": "Şoför Bir"
        }
      }
    ]
  }
}
```

---

### 2.2 Dönemsel Rapor

**GET** `/api/reports`

| Query Parametresi | Değerler | Varsayılan | Açıklama |
|---|---|---|---|
| `period` | `daily`, `weekly`, `monthly`, `quarterly` | `monthly` | Raporlama dönemi |
| `studio_id` | integer | — | Stüdyo filtresi (opsiyonel) |

**Response `200`**

```json
{
  "status": "success",
  "code": 200,
  "data": {
    "selected_period": "Bu Ay",
    "stats": {
      "total_appointments": 1284,
      "cancelled": 42,
      "completed": 1210,
      "this_week": 312
    },
    "weekly_data": [
      { "day": "Pzt", "value": 60 },
      { "day": "Sal", "value": 90 },
      { "day": "Çar", "value": 140 },
      { "day": "Per", "value": 105 },
      { "day": "Cum", "value": 95 },
      { "day": "Cmt", "value": 45 },
      { "day": "Paz", "value": 20 }
    ],
    "performance": [
      {
        "name": "Aslı Demir",
        "role": "Çalışan",
        "appointments": 142,
        "rating": 4.9
      },
      {
        "name": "Mert Kaya",
        "role": "Şoför",
        "appointments": 128,
        "rating": 4.8
      },
      {
        "name": "Selin Yılmaz",
        "role": "Çalışan",
        "appointments": 96,
        "rating": 4.7
      }
    ],
    "insight": "Bu hafta randevu yoğunluğu %12 arttı."
  }
}
```

**`selected_period` değerleri:**

| `period` | `selected_period` |
|---|---|
| `daily` | Bugün |
| `weekly` | Bu Hafta |
| `monthly` | Bu Ay |
| `quarterly` | Son 3 Ay |

> `performance` listesi dönem içinde en çok randevu oluşturan 5 kullanıcıyı gösterir.  
> `insight` metni bu hafta ile geçen hafta arasındaki değişime göre otomatik üretilir.  
> `rating` kullanıcıda kayıtlı değer yoksa `null` döner.

---

## 3. Kullanıcı Yönetimi

### 3.1 Kullanıcıları Listele

**GET** `/api/studios/{studio_id}/users`

> Yetki: `admin`, `yonetici`

**Response `200`**

```json
{
  "data": [
    {
      "id": 5,
      "name": "Hasan Çalışan",
      "email": "hasan@example.com",
      "phone": "5551234567",
      "role": "calisan",
      "profile_image": null,
      "studio_id": 1,
      "status": "working",
      "is_active": true
    }
  ]
}
```

---

### 3.2 Kullanıcı Oluştur

**POST** `/api/users`

> Yetki: `admin`, `yonetici`

```json
{
  "name": "Mehmet",
  "surname": "Şoför",
  "email": "mehmet@example.com",
  "phone": "5551234567",
  "role": "sofor",
  "studio_id": 1,
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response `201`**

```json
{
  "message": "Kullanıcı başarıyla oluşturuldu.",
  "data": {
    "id": 6,
    "name": "Mehmet Şoför",
    "email": "mehmet@example.com",
    "role": "sofor",
    "is_active": true
  }
}
```

---

### 3.3 Kullanıcı Güncelle

**PATCH** `/api/studios/{studio_id}/users/{user_id}`

> Yetki: `admin`, `yonetici`  
> Tüm alanlar opsiyoneldir.

```json
{
  "name": "Mehmet Yeni",
  "surname": "Soyadı",
  "email": "mehmet@example.com",
  "phone": "5559876543",
  "role": "supervisor",
  "status": "break",
  "is_active": false,
  "profile_image": "profiles/mehmet.png"
}
```

> `admin` dışındaki roller `admin` veya `yonetici` rolü atayamaz.

**Response `200`**

```json
{
  "message": "Kullanıcı güncellendi.",
  "data": {
    "id": 6,
    "name": "Mehmet Yeni",
    "email": "mehmet@example.com",
    "role": "supervisor",
    "profile_image": null,
    "studio_id": 1,
    "status": "break",
    "is_active": false
  }
}
```

---

### 3.4 Stüdyoları Listele (Genel Bakış)

**GET** `/api/studios/overview`

> Yetki: `admin`, `yonetici`

**Response `200`**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Merkez Stüdyo",
      "location": "Antalya",
      "slug": "merkez-studyo",
      "logo_path": null,
      "notification_lead_minutes": 30,
      "shop": {
        "id": 1,
        "name": "Merkez Dükkan"
      },
      "total_staff_count": 8,
      "active_staff_count": 3,
      "appointments_count": 22
    }
  ]
}
```

> `total_staff_count`: stüdyoya kayıtlı toplam personel sayısı.  
> `active_staff_count`: şu an aktif (is_active = true) personel sayısı.

---

### 3.5 Stüdyo Güncelle

**PATCH** `/api/studios/{studio_id}`

> Yetki: `admin`, `yonetici`  
> Tüm alanlar opsiyoneldir.

```json
{
  "name": "Merkez Stüdyo (Yeni)",
  "location": "Kemer",
  "logo_path": "logos/merkez.png",
  "notification_lead_minutes": 45
}
```

**Response `200`**

```json
{
  "message": "Stüdyo güncellendi.",
  "data": {
    "id": 1,
    "name": "Merkez Stüdyo (Yeni)",
    "location": "Kemer",
    "slug": "merkez-studyo",
    "logo_path": "logos/merkez.png",
    "notification_lead_minutes": 45,
    "shop_id": 1
  }
}
```

---

### 3.6 Stüdyo Oluştur

**POST** `/api/studios`

> Yetki: `admin`, `yonetici`

```json
{
  "shop_id": 1,
  "name": "Yeni Stüdyo",
  "location": "Antalya",
  "notification_lead_minutes": 30
}
```

> `shop_id` zorunludur. Şirketin `max_studio_count` limitine ulaşılmışsa `422` döner.

**Response `422`** — limit aşımı

```json
{
  "status": "error",
  "code": 422,
  "message": "Stüdyo limitinize ulaştınız. Daha fazla stüdyo oluşturmak için lütfen admin ile iletişime geçin.",
  "data": { "current": 10, "limit": 10 }
}
```

**Response `201`**

```json
{
  "status": "success",
  "code": 201,
  "message": "Stüdyo oluşturuldu.",
  "data": {
    "id": 3,
    "name": "Yeni Stüdyo",
    "location": "Antalya",
    "slug": "yeni-studyo-ab12c",
    "shop_id": 1
  }
}
```

---

### 3.6b Stüdyo Sil

**DELETE** `/api/studios/{studio_id}`

> Yetki: `admin`, `yonetici`

**Response `200`**

```json
{
  "message": "Stüdyo silindi."
}
```

---

### 3.7 Stüdyo Seçenekleri (Dropdown)

**GET** `/api/studios/options`

**Response `200`**

```json
{
  "data": [
    { "id": 1, "name": "Merkez Stüdyo" },
    { "id": 2, "name": "Şube 1" }
  ]
}
```

---

### 3.8 Kullanıcı Seçenekleri (Dropdown)

**GET** `/api/users/options`

| Query Parametresi | Örnek | Açıklama |
|---|---|---|
| `roles` | `yonetici,supervisor` | Virgülle ayrılmış rol filtresi (opsiyonel) |

**Response `200`**

```json
{
  "data": [
    {
      "id": 2,
      "name": "Yönetici Bir",
      "email": "manager@example.com",
      "role": "yonetici"
    }
  ]
}
```

---

## 4. Şirket Yönetimi (Admin Only)

### 4.1 Şirketleri Listele

**GET** `/api/companies`

> Yetki: yalnızca `admin`

**Response `200`**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Dövme Şirketi A.Ş.",
      "address": "Antalya, Türkiye",
      "phone": "05321234567",
      "email": "info@dovme.com",
      "is_active": true,
      "max_shop_count": 5,
      "max_studio_count": 10,
      "shop_count": 2,
      "studio_count": 4,
      "appointment_count": 320
    }
  ]
}
```

> `max_shop_count` / `max_studio_count`: 0 ise sınırsız demektir.  
> `shop_count` / `studio_count`: şu an kayıtlı aktif sayılar.

---

### 4.2 Şirket Oluştur

**POST** `/api/companies`

> Yetki: yalnızca `admin`

```json
{
  "name": "Yeni Şirket A.Ş.",
  "address": "İstanbul, Türkiye",
  "phone": "05329876543",
  "email": "info@yenisirket.com",
  "max_shop_count": 3,
  "max_studio_count": 6
}
```

**Response `201`**

```json
{
  "message": "Şirket oluşturuldu.",
  "data": { "id": 2, "name": "Yeni Şirket A.Ş." }
}
```

---

### 4.3 Şirket Güncelle

**PATCH** `/api/companies/{company_id}`

> Yetki: yalnızca `admin`  
> Tüm alanlar opsiyoneldir.

```json
{
  "name": "Şirket Adı (Güncel)",
  "max_shop_count": 5,
  "max_studio_count": 10
}
```

**Response `200`**

```json
{
  "message": "Şirket güncellendi.",
  "data": { "id": 1, "name": "Şirket Adı (Güncel)" }
}
```

---

## 5. Dükkan Yönetimi

### 5.1 Dükkanları Listele

**GET** `/api/shops`

> `admin` tüm dükkanları görür. `yonetici` ve `supervisor` yalnızca kendi dükkanlarını görür.

**Response `200`**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Merkez Dükkan",
      "location": "Antalya",
      "is_active": true,
      "manager": {
        "id": 2,
        "name": "Yönetici Bir",
        "email": "manager@example.com",
        "role": "yonetici"
      },
      "studios": [
        { "id": 1, "name": "Merkez Stüdyo" }
      ]
    }
  ]
}
```

---

### 5.2 Dükkan Oluştur

**POST** `/api/shops`

> Yetki: yalnızca `admin`

```json
{
  "company_id": 1,
  "name": "Sahil Dükkan",
  "location": "Antalya",
  "manager_user_id": 2
}
```

> `company_id` zorunludur. Şirketin `max_shop_count` limitine ulaşılmışsa `422` döner.

**Response `422`** — limit aşımı

```json
{
  "message": "Dükkan limitinize ulaştınız. Daha fazla dükkan oluşturmak için lütfen admin ile iletişime geçin.",
  "data": { "current": 3, "limit": 3 }
}
```

**Response `201`**

```json
{
  "message": "Dükkan oluşturuldu.",
  "data": {
    "id": 3,
    "name": "Sahil Dükkan",
    "location": "Antalya",
    "is_active": true
  }
}
```

---

### 5.3 Dükkan Güncelle

**PATCH** `/api/shops/{shop_id}`

> `admin` her dükkanı güncelleyebilir. `yonetici` yalnızca kendi dükkanını güncelleyebilir.

```json
{
  "name": "Sahil Dükkan (Yeni)",
  "location": "Kemer",
  "manager_user_id": 3
}
```

**Response `200`**

```json
{
  "message": "Dükkan güncellendi.",
  "data": {
    "id": 3,
    "name": "Sahil Dükkan (Yeni)",
    "location": "Kemer"
  }
}
```

### 5.4 Dükkan Sil

**DELETE** `/api/shops/{shop_id}`

> Yetki: yalnızca `admin`

**Response `200`**

```json
{
  "message": "Dükkan silindi."
}
```

---

## 6. Randevular

### 6.1 Randevuları Listele

**GET** `/api/studios/{studio_id}/appointments`

> Yetki: `admin`, `yonetici`, `supervisor`, `calisan`, `sofor`  
> `sofor` rolü yalnızca kendine atanmış randevuları görür.

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
      "notes": "Ön kapıdan alınacak.",
      "source_image_path": "uploads/slips/slip_123.jpg",
      "assigned_driver_user_id": 4,
      "driver": {
        "id": 4,
        "name": "Şoför Bir",
        "phone": "5559998877",
        "rating": 4.8
      },
      "studio": "Merkez Stüdyo",
      "created_at": "2026-05-04T08:00:00+03:00"
    }
  ]
}
```

---

### 6.2 Müşteri Geçmişi Kontrol

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

**Response `200`**

```json
{
  "data": {
    "is_old_customer": true,
    "last_appointment_id": 12,
    "customer_notes": "VIP Müşteri"
  }
}
```

---

### 6.3 Randevu Detayı

**GET** `/api/studios/{studio_id}/appointments/{appointment_id}`

> Yetki: `admin`, `yonetici`, `supervisor`, `calisan`, `sofor`

**Response `200`**

```json
{
  "data": {
    "id": 1,
    "appointment_type": "standard",
    "full_name": "John Doe",
    "date": "2026-05-05",
    "time": "10:00",
    "place": "Hilton",
    "driver": {
      "id": 4,
      "name": "Şoför",
      "surname": "Bir"
    },
    "created_by": {
      "id": 2,
      "name": "Çalışan",
      "surname": "Bir"
    },
    "status": "confirmed",
    "driver_status": null
  }
}
```

---

### 6.4 Randevu Oluştur

**POST** `/api/studios/{studio_id}/appointments`

```json
{
  "customer": {
    "first_name": "John",
    "last_name": "Doe",
    "phone_country_code": "+90",
    "phone_number": "5550001122",
    "hotel_name": "Hilton",
    "room_number": "402"
  },
  "pax": 2,
  "appointment_at": "2026-05-05T10:00:00+03:00",
  "appointment_type": "standard",
  "notes": "Ön kapıdan alınacak.",
  "source_image_path": "uploads/slips/slip_123.jpg",
  "assigned_driver_user_id": 4
}
```

**Response `201`**

```json
{
  "message": "Randevu oluşturuldu.",
  "data": {
    "id": 15,
    "status": "pending"
  }
}
```

---

### 6.5 Randevu Güncelle

**PATCH** `/api/studios/{studio_id}/appointments/{appointment_id}`

```json
{
  "status": "completed",
  "assigned_driver_user_id": 5,
  "notes": "Müşteri erken geldi."
}
```

**Response `200`**

```json
{
  "message": "Randevu güncellendi.",
  "data": {
    "id": 15,
    "status": "completed"
  }
}
```

---

### 6.6 Randevu Sil

**DELETE** `/api/studios/{studio_id}/appointments/{appointment_id}`

**Response `200`**

```json
{
  "message": "Randevu silindi."
}
```

---

### 6.7 Şoför Aksiyon Güncelle

**PATCH** `/api/studios/{studio_id}/appointments/{appointment_id}/driver-action`

> Yetki: yalnızca `sofor`  
> Şoför yalnızca kendine atanmış randevularda aksiyon alabilir.

```json
{
  "driver_status": "picked_up"
}
```

| `driver_status` | Anlamı | Yan Etki |
|---|---|---|
| `picked_up` | Aldım | Ana durum değişmez |
| `dropped_off` | Bıraktım | Ana `status` → `completed` |
| `cancelled` | İptal ettim | Ana `status` → `cancelled` |

**Response `200`**

```json
{
  "message": "Durum güncellendi.",
  "data": {
    "id": 1,
    "status": "completed",
    "driver_status": "dropped_off"
  }
}
```

**Response `403`** — başkasına ait randevuya aksiyon alınmaya çalışılırsa döner.

---

### 6.8 Şoförün Kendi Randevuları

**GET** `/api/my-appointments`

> Yetki: yalnızca `sofor`  
> Stüdyodan bağımsız olarak giriş yapan şoföre atanmış **tüm** randevuları döner. Şoför hangi stüdyoda kayıtlı olduğundan bağımsız, kendine atanan her randevuyu bu endpoint üzerinden görebilir.

**Response `200`**

```json
{
  "data": [
    {
      "id": 1,
      "studio": {
        "id": 1,
        "name": "Merkez Stüdyo"
      },
      "customer": {
        "first_name": "John",
        "last_name": "Doe",
        "phone_country_code": "+90",
        "phone_number": "5550001122",
        "hotel_name": "Hilton",
        "room_number": "402",
        "customer_notes": "VIP Müşteri"
      },
      "place": "Hilton Giriş",
      "pax": 2,
      "appointment_at": "2026-05-05T10:00:00+03:00",
      "status": "confirmed",
      "driver_status": null,
      "notes": "Ön kapıdan alınacak.",
      "created_by": {
        "id": 2,
        "name": "Çalışan Bir"
      },
      "created_at": "2026-05-04T08:00:00+03:00"
    }
  ]
}
```

> Randevular `appointment_at` alanına göre artan sırada (eskiden yeniye) gelir.  
> `driver_status` değerleri: `null` (henüz aksiyon yok), `picked_up`, `dropped_off`, `cancelled`.

---

### 6.9 Randevu Destek Verisi (Dropdown)

**GET** `/api/studios/{studio_id}/appointment-support`

Randevu oluşturma/güncelleme ekranları için sürücü listesi ve diğer kaynakları döner.

**Response `200`**

```json
{
  "data": {
    "drivers": [
      {
        "id": 4,
        "name": "Şoför Bir",
        "phone": "5559998877"
      }
    ]
  }
}
```

---

## 7. OCR — Fiş Okuma

**POST** `/api/ocr/appointment-slip`

> Kimlik doğrulaması gerekmez.  
> `Content-Type: multipart/form-data`

| Alan | Tip | Zorunlu |
|---|---|---|
| `image` | file | ✓ |

**Response `200`**

```json
{
  "data": {
    "first_name": "Fabian",
    "last_name": "Uzun",
    "hotel_name": "Ramada",
    "room_number": "3211",
    "pax": 3,
    "date": "2026-05-05",
    "time": "17:30"
  }
}
```

---

## Endpoint Özeti

| Yöntem | Endpoint | Yetki | Açıklama |
|---|---|---|---|
| POST | `/api/login` | — | Giriş yap |
| GET | `/api/me` veya `/api/profile` | Tümü | Profil getir |
| PATCH | `/api/me` veya `/api/profile` | Tümü | Profil güncelle |
| POST | `/api/logout` | Tümü | Çıkış yap |
| GET | `/api/home` | Tümü | Dashboard özeti |
| GET | `/api/reports` | Tümü | Dönemsel rapor |
| GET | `/api/companies` | Admin | Şirketleri listele |
| POST | `/api/companies` | Admin | Şirket oluştur |
| PATCH | `/api/companies/{id}` | Admin | Şirket güncelle / limit ayarla |
| GET | `/api/studios/overview` | Admin, Yönetici | Stüdyoları listele (personel + randevu sayısıyla) |
| POST | `/api/studios` | Admin, Yönetici | Stüdyo oluştur |
| PATCH | `/api/studios/{id}` | Admin, Yönetici | Stüdyo güncelle |
| DELETE | `/api/studios/{id}` | Admin, Yönetici | Stüdyo sil |
| GET | `/api/studios/options` | Tümü | Stüdyo dropdown |
| GET | `/api/users/options` | Admin, Yönetici | Kullanıcı dropdown |
| GET | `/api/shops` | Tümü | Dükkanları listele |
| POST | `/api/shops` | Admin | Dükkan oluştur |
| PATCH | `/api/shops/{id}` | Admin, Yönetici | Dükkan güncelle |
| DELETE | `/api/shops/{id}` | Admin | Dükkan sil |
| GET | `/api/studios/{id}/users` | Admin, Yönetici | Kullanıcıları listele |
| POST | `/api/users` | Admin, Yönetici | Kullanıcı oluştur |
| PATCH | `/api/studios/{id}/users/{id}` | Admin, Yönetici | Kullanıcı güncelle |
| GET | `/api/studios/{id}/appointments` | Admin, Yönetici, Supervisor, Çalışan, **Şoför** | Randevuları listele (şoför: sadece kendine atananlar) |
| POST | `/api/studios/{id}/appointments` | Admin, Yönetici, Supervisor, Çalışan | Randevu oluştur |
| GET | `/api/studios/{id}/appointments/{id}` | Admin, Yönetici, Supervisor, Çalışan, **Şoför** | Randevu detayı |
| PATCH | `/api/studios/{id}/appointments/{id}` | Admin, Yönetici, Supervisor, Çalışan | Randevu güncelle |
| DELETE | `/api/studios/{id}/appointments/{id}` | Admin, Yönetici, Supervisor, Çalışan | Randevu sil |
| PATCH | `/api/studios/{id}/appointments/{id}/driver-action` | **Şoför** | Şoför aksiyonu (aldım / bıraktım / iptal) |
| GET | `/api/my-appointments` | **Şoför** | Kendine atanan tüm randevuları listele (stüdyodan bağımsız) |
| POST | `/api/studios/{id}/appointments/check-customer` | Admin, Yönetici, Supervisor, Çalışan | Müşteri geçmişi |
| GET | `/api/studios/{id}/appointment-support` | Admin, Yönetici, Supervisor, Çalışan | Sürücü dropdown |
| POST | `/api/ocr/appointment-slip` | — | Fiş OCR |

---

## Test Hesapları

| E-posta | Şifre | Rol |
|---|---|---|
| admin@example.com | 123456 | Admin |
| manager@example.com | 123456 | Yönetici |
| supervisor@example.com | 123456 | Supervisor |
| driver@example.com | 123456 | Şoför |
| employee@example.com | 123456 | Çalışan |
