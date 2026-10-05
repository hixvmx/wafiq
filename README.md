# وافِق — Wafiq

أرسل عرض السعر أو الفاتورة عبر واتساب أو البريد برابط متتبَّع، واعرف متى فتحه عميلك، واحصل على موافقته بضغطة.

Send quotations and invoices by WhatsApp or email with a tracked link, see when the client opens them, and get their approval online. Arabic (RTL) interface.

## التثبيت / Installation

1. أنشئ قاعدة بيانات MySQL فارغة.
2. ارفع الملفات واجعل النطاق يشير إلى مجلد `public`.
3. افتح النطاق في المتصفح واتبع معالج التثبيت.
4. أضف مهمة Cron واحدة كل دقيقة (يعرضها المعالج في آخر خطوة).

الدليل الكامل بالعربية في مجلد [`docs/`](docs/README.md).

Requirements: PHP 8.2+ (pdo_mysql, mbstring, gd, intl, fileinfo, openssl, curl, zip), MySQL 5.7+ / MariaDB 10.3+, HTTPS, one cron entry.

## الرخصة / Licence

انظر [`LICENSE.txt`](LICENSE.txt) وحقوق الطرف الثالث في [`CREDITS.md`](CREDITS.md).

## For developers

```
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed            # or: --seeder=DemoSeeder for the Arabic demo company
php artisan wafiq:login-link owner@wafiq.test
composer run dev
```

Tests: `php artisan test` (run with `APP_EDITION=saas` too) and `npm run test:js`.
Release package: `php scripts/build-release.php 1.0.0` → `build/wafiq-1.0.0.zip`.
