# یادآوری‌های CRM (Tasks & Follow-ups)

## اجرای زمان‌بند

در محیط توسعه:

```bash
php artisan schedule:work
```

در production، cron هر دقیقه:

```bash
* * * * * cd /path/to/RahbarCrm && php artisan schedule:run >> /dev/null 2>&1
```

دستور پردازش یادآوری‌ها:

```bash
php artisan reminders:process
```

## صف (اختیاری)

اعلان‌ها هم‌اکنون به‌صورت همزمان (sync) ارسال می‌شوند. برای مقیاس بالاتر در `.env`:

```
QUEUE_CONNECTION=database
```

سپس:

```bash
php artisan queue:work
```

## کانال‌ها

- **درون‌اپ:** `GET /api/v1/notifications` — آیکن زنگ در navbar (layout عمودی و افقی)
- **مرورگر:** با اجازه `Notification` API هنگام دریافت اعلان جدید

## پیام گروهی (مدیر / مالک)

- **ارسال:** `POST /api/v1/notifications/broadcast` با `{ title, body, kind?: broadcast|system }`
- **UI:** تنظیمات مجموعه → تب «پیام به تیم»
- همه اعضای فعال مجموعه (`left_at` خالی) اعلان دریافت می‌کنند
- تاریخچه: `GET /api/v1/notifications/broadcasts`

پس از deploy، کاربران برای فیلد `canBroadcast` یک‌بار از مجموعه خارج و دوباره وارد شوند (یا دوباره login).

## فیلدهای مرتبط

| موجودیت | فیلد زمان | فیلد یادآوری |
|---------|-----------|--------------|
| Task | `due_at` | `reminder_at` |
| Lead | `next_follow_up_at` | `follow_up_reminder_at` (پیش‌فرض: ۱ ساعت قبل) |
| Deal | `next_follow_up_at` | `follow_up_reminder_at` |
| Activity | `scheduled_at` | `reminder_at` (پیش‌فرض: ۱۵ دقیقه قبل) |
