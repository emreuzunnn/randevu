# Randevu Mobil Uygulamasi - Backend API Spesifikasyonlari

Bu dokuman mobil uygulamanin bekledigi endpointleri, istek alanlarini ve donen JSON formatlarini guncel haliyle tanimlar.

Base URL:

`http://127.0.0.1:8000/api`

## Genel Kurallar

1. `POST /api/login` haric tum endpointlerde `Authorization: Bearer {token}` header'i kullanilir.
2. Roller: `admin`, `yonetici`, `supervisor`, `calisan`, `sofor`
3. Kullanici durumlari: `working`, `break`, `transfer`
4. Randevu durumlari: `pending`, `confirmed`, `completed`, `cancelled`, `rescheduled`

## Yetki Hiyerarsisi

- `admin`: tum dukkanlari, studyolari, kullanicilari ve randevulari yonetir.
- `yonetici`: kendisine bagli dukkanlari, bu dukkanlara bagli studyolari, kullanicilari ve randevulari yonetir.
- `supervisor`: kendisine bagli dukkanin sadece randevu tarafini yonetir.
- Her `dukkan` birden fazla `studio` icerebilir.
- Calisanlar, soforler ve diger personeller `studio` bazinda baglidir.
- Bir supervisor kullanici/studyo/dukkan ayarlarini degistiremez.

## 1. Auth ve Profil

### 1.1 Login

**POST** `/api/login`

Request:

```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

Response:

```json
{
  "message": "Giris basarili.",
  "data": {
    "token": "1|xxxxxxxxx",
    "token_type": "Bearer",
    "studio_id": 1,
    "user": {
      "id": 1,
      "name": "Ahmet Yilmaz",
      "email": "ahmet@example.com",
      "role": "admin",
      "profile_image": "https://example.com/avatar.jpg",
      "status": "working",
      "is_active": true
    }
  }
}
```

### 1.2 Profil Getir

**GET** `/api/profile`

Response:

```json
{
  "data": {
    "id": 1,
    "name": "Ahmet Yilmaz",
    "email": "ahmet@example.com",
    "role": "admin",
    "profile_image": "https://example.com/avatar.jpg",
    "status": "working",
    "location": "Merkez Ofis",
    "is_active": true,
    "created_at": "2026-04-27T10:00:00+03:00"
  }
}
```

## 2. Home ve Dashboard

### 2.1 Anasayfa Ozeti

**GET** `/api/home`

Opsiyonel query:

- `date_from=2026-04-27`
- `date_to=2026-04-27`
- `studio_id=1`

Response:

```json
{
  "data": {
    "summary": {
      "total_appointments": 12,
      "cancelled_appointments": 2,
      "active_staff_count": 5,
      "transfer_count": 7
    },
    "today_appointments": [
      {
        "id": 1,
        "customer": {
          "first_name": "Fabian",
          "last_name": "Uzun",
          "hotel_name": "Ramada"
        },
        "pax": 3,
        "appointment_at": "2026-04-28T17:30:00+03:00",
        "status": "pending",
        "studio": "Merkez Studio",
        "driver": {
          "id": 4,
          "name": "Sofor Bir"
        }
      }
    ]
  }
}
```

## 3. Kullanici Yonetimi

### 3.1 Kullanicilari Listele

**GET** `/api/studios/{studio_id}/users`

Response:

```json
{
  "data": [
    {
      "id": 5,
      "name": "Hasan Calisan",
      "email": "hasan@example.com",
      "role": "calisan",
      "profile_image": null,
      "location": "Studyo 1",
      "status": "working",
      "is_active": true
    }
  ]
}
```

### 3.2 Kullanici Olustur

**POST** `/api/users`

Request:

```json
{
  "name": "Mehmet",
  "surname": "Sofor",
  "email": "mehmet@example.com",
  "phone": "5551234567",
  "role": "sofor",
  "studio_id": 1,
  "password": "password123",
  "password_confirmation": "password123"
}
```

Response:

```json
{
  "message": "Kullanici basariyla olusturuldu.",
  "data": {
    "id": 6,
    "name": "Mehmet Sofor",
    "email": "mehmet@example.com",
    "role": "sofor",
    "is_active": true
  }
}
```

### 3.3 Kullanici Guncelle

**PATCH** `/api/studios/{studio_id}/users/{user_id}`

Request:

```json
{
  "name": "Mehmet Yeni",
  "role": "supervisor",
  "status": "break",
  "is_active": false
}
```

Response:

```json
{
  "message": "Kullanici guncellendi.",
  "data": {
    "id": 6,
    "name": "Mehmet Sofor",
    "email": "mehmet@example.com",
    "role": "supervisor",
    "profile_image": null,
    "location": "Merkez Studio",
    "status": "break",
    "is_active": false
  }
}
```

### 3.4 Studyo Secenekleri

**GET** `/api/studios/options`

Response:

```json
{
  "data": [
    { "id": 1, "name": "Merkez Studio" },
    { "id": 2, "name": "Sube 1" }
  ]
}
```

### 3.5 Dukkanlari Listele

`GET /api/shops`

Not:

- `admin` tum dukkanlari gorur.
- `yonetici` ve `supervisor` sadece kendi dukkanlarini gorur.

Response:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Merkez Dukkan",
      "location": "Istanbul",
      "is_active": true,
      "manager": {
        "id": 2,
        "name": "Yonetici Bir",
        "email": "manager@example.com",
        "role": "yonetici"
      },
      "studios": [
        {
          "id": 1,
          "name": "Merkez Studio"
        }
      ]
    }
  ]
}
```

### 3.6 Dukkan Olustur

`POST /api/shops`

Not:

- Sadece `admin`

Request:

```json
{
  "name": "Sahil Dukkan",
  "location": "Antalya",
  "manager_user_id": 2
}
```

### 3.7 Dukkan Guncelle

`PATCH /api/shops/{shop_id}`

Not:

- `admin` her dukkanı guncelleyebilir.
- `yonetici` ve `supervisor` sadece kendi dukkanini guncelleyebilir.

## 4. Randevular

### 4.1 Randevulari Listele

**GET** `/api/studios/{studio_id}/appointments`

Response:

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
        "customer_notes": "VIP Musteri"
      },
      "pax": 2,
      "appointment_at": "2026-04-28T10:00:00+03:00",
      "status": "confirmed",
      "notes": "On kapi dan alinacak",
      "source_image_path": "uploads/slips/slip_123.jpg",
      "assigned_driver_user_id": 4,
      "driver": {
        "id": 4,
        "name": "Sofor Bir",
        "phone": "5559998877",
        "rating": 4.8
      },
      "studio": "Merkez Studio",
      "created_at": "2026-04-27T08:00:00+03:00"
    }
  ]
}
```

### 4.2 Musteri Gecmisi Kontrol

**POST** `/api/studios/{studio_id}/appointments/check-customer`

Request:

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

Response:

```json
{
  "data": {
    "is_old_customer": true,
    "last_appointment_id": 12,
    "customer_notes": "VIP Musteri"
  }
}
```

### 4.3 Randevu Detayi

**GET** `/api/studios/{studio_id}/appointments/{appointment_id}`

Response:

```json
{
  "data": {
    "id": 1,
    "appointment_type": "vip",
    "full_name": "John Doe",
    "date": "2026-04-28",
    "time": "10:00",
    "place": "Hilton",
    "driver": {
      "id": 4,
      "name": "Sofor",
      "surname": "Bir"
    },
    "created_by": {
      "id": 2,
      "name": "Calisan",
      "surname": "Bir"
    },
    "status": "confirmed"
  }
}
```

### 4.4 Randevu Olustur

**POST** `/api/studios/{studio_id}/appointments`

Request:

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
  "appointment_at": "2026-04-28T10:00:00+03:00",
  "appointment_type": "standard",
  "notes": "On kapi dan alinacak",
  "source_image_path": "uploads/slips/slip_123.jpg",
  "assigned_driver_user_id": 4
}
```

Response:

```json
{
  "message": "Randevu olusturuldu.",
  "data": {
    "id": 15,
    "status": "pending"
  }
}
```

### 4.5 Randevu Guncelle

**PATCH** `/api/studios/{studio_id}/appointments/{appointment_id}`

Request:

```json
{
  "status": "completed",
  "assigned_driver_user_id": 5,
  "notes": "Musteri erken geldi."
}
```

Response:

```json
{
  "message": "Randevu guncellendi.",
  "data": {
    "id": 15,
    "status": "completed"
  }
}
```

### 4.6 Randevu Sil

**DELETE** `/api/studios/{studio_id}/appointments/{appointment_id}`

Response:

```json
{
  "message": "Randevu silindi."
}
```

## 5. OCR

### 5.1 Fis Okuma

**POST** `/api/ocr/appointment-slip`

Content-Type: `multipart/form-data`

Body:

- `image` (file)

Response:

```json
{
  "data": {
    "first_name": "Fabian",
    "last_name": "Uzun",
    "hotel_name": "Ramada",
    "room_number": "3211",
    "pax": 3,
    "date": "2026-04-28",
    "time": "17:30"
  }
}
```

## Test Hesaplari

- `admin@example.com` / `123456`
- `manager@example.com` / `123456`
- `supervisor@example.com` / `123456`
- `driver@example.com` / `123456`
- `employee@example.com` / `123456`

## Not

Bu projede mevcut yapi artik `dukkan -> studio -> personel/randevu` seklindedir.
