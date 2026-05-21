# Teacher Payment Notification Queue

Feature ini menambah penghantaran resit bayaran kepada guru kelas melalui queue Laravel, bukan `wa.me` segera.

## Worker

Gunakan worker khas berikut:

```bash
php artisan queue:work --queue=teacher-notification --sleep=3 --tries=3
```

## Nota operasi

- Job ini berkongsi lock `whatsapp-api-send-lock` dengan queue WhatsApp sedia ada.
- Jarak penghantaran masih mengikuti `whatsapp.minimumSendGapSeconds()` melalui `WhatsAppMessageQueueService`.
- Elakkan menambah worker selari yang menggunakan API key Wasender yang sama tanpa had kelajuan.
