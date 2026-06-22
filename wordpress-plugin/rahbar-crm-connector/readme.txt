=== Rahbar CRM Connector ===
Contributors: rahbarcrm
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

اتصال ووکامرس به Rahbar CRM بدون نیاز به whitelist IP در هاست.

== نصب ==

1. پوشه `rahbar-crm-connector` را در `wp-content/plugins/` کپی کنید.
2. پلاگین را از پنل وردپرس فعال کنید.
3. در CRM: یکپارچه‌سازی → ووکامرس → حالت «پلاگین وردپرس» را انتخاب و ذخیره کنید.
4. Bridge Token و Bridge Secret را از CRM کپی کنید.
5. در وردپرس: WooCommerce → Rahbar CRM → تنظیمات را وارد و «تست اتصال» بزنید.

== چرا این پلاگین؟ ==

بسیاری از هاست‌های ایرانی (BitNinja و ...) درخواست REST API از سرور CRM را مسدود می‌کنند.
این پلاگین داده را از داخل وردپرس به CRM **ارسال** می‌کند — CRM دیگر نیازی به تماس با وردپرس ندارد.
