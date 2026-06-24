# WhatsApp Cloud API Webhook

## Endpoint

Meta panelinde callback URL olarak şunu gir:

```text
https://api.tattodesk.com/webhook/whatsapp
```

## .env

```env
WHATSAPP_VERIFY_TOKEN=uzun-ve-rastgele-bir-token
WHATSAPP_ACCESS_TOKEN=EAAG...
WHATSAPP_PHONE_NUMBER_ID=123456789

# Opsiyonel ama production için önerilir.
WHATSAPP_APP_SECRET=meta-app-secret
WHATSAPP_VALIDATE_SIGNATURE=true
```

## Meta Webhook Kurulumu

1. Meta for Developers panelinde uygulamayı aç.
2. WhatsApp > Configuration veya Webhooks bölümüne gir.
3. Callback URL alanına `https://api.tattodesk.com/webhook/whatsapp` yaz.
4. Verify Token alanına `.env` içindeki `WHATSAPP_VERIFY_TOKEN` değerini yaz.
5. Webhook doğrulamasını kaydet.
6. WhatsApp Business Account için `messages` event aboneliğini aç.
7. Production ortamında `WHATSAPP_APP_SECRET` ve `WHATSAPP_VALIDATE_SIGNATURE=true` ayarla.
8. Config cache kullanıyorsan sunucuda `php artisan config:clear` veya deploy akışındaki config cache komutunu çalıştır.

## Loglar

Webhook payloadları ayrı log dosyasına yazılır:

```text
storage/logs/whatsapp.log
```

POST endpoint mesajları, teslim edildi/okundu durumlarını, hata eventlerini ve ham payload özetini loglar. Endpoint işleme sırasında hata alsa bile Meta'ya hızlıca `HTTP 200 OK` döndürür. İmza doğrulaması aktifse ve imza geçersizse `HTTP 403` döner.
