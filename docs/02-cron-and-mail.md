# مهمة Cron والبريد

## مهمة Cron (مرة واحدة فقط)

يحتاج وافِق إلى مهمة واحدة تعمل كل دقيقة. هي المسؤولة عن:

- تحويل العروض التي تجاوزت تاريخ صلاحيتها إلى «منتهي».
- إرسال رسائل البريد المؤجلة، مثل إشعار «أشار إليك فلان» (@).

من **cPanel ← Cron Jobs**:

1. اختر **Once Per Minute (\* \* \* \* \*)**.
2. الصق في خانة Command السطر الذي عرضه معالج التثبيت، وهو بهذا الشكل:

```
cd /home/user/wafiq && php artisan schedule:run >> /dev/null 2>&1
```

> إن ظهر خطأ «php: command not found» استخدم المسار الكامل، مثل `/usr/local/bin/php` أو `/opt/cpanel/ea-php82/root/usr/bin/php`.

بدون هذه المهمة يعمل النظام، لكن العروض المنتهية لا يتغير شكلها في القوائم إلا عند فتحها، وتتأخر رسائل الإشارة.

## البريد

يرسل وافِق بالبريد: روابط الدخول، الدعوات، المستندات للعملاء، وإشعارات الإشارة.

### أفضل إعداد على cPanel

1. أنشئ حساب بريد على نطاقك، مثل `noreply@yourcompany.com` (cPanel ← Email Accounts).
2. من **Connect Devices** انسخ: الخادم (Outgoing Server)، المنفذ (465)، اسم المستخدم (البريد كاملاً) وكلمة المرور.
3. أدخلها في خطوة البريد في المعالج.

لتحسين الوصول إلى صندوق الوارد بدلاً من «غير المرغوب فيه»: من **cPanel ← Email Deliverability** تأكد أن سجلات **SPF** و**DKIM** صحيحة (زر Repair إن ظهر تحذير).

### تغيير إعدادات البريد لاحقاً

الإعدادات محفوظة في ملف `.env` داخل مجلد وافِق، في الأسطر التي تبدأ بـ `MAIL_`:

```
MAIL_MAILER=smtp
MAIL_HOST=mail.yourcompany.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=noreply@yourcompany.com
MAIL_PASSWORD="كلمة المرور"
MAIL_FROM_ADDRESS=noreply@yourcompany.com
```

استخدم `MAIL_SCHEME=smtps` مع المنفذ 465، و`MAIL_SCHEME=smtp` مع 587.

### لم يصلني رابط الدخول والبريد لا يعمل

من الطرفية في cPanel (Terminal) داخل مجلد وافِق:

```
php artisan wafiq:login-link you@yourcompany.com
```

يطبع الأمر رابط دخول صالحاً لمدة 15 دقيقة. ادخل به ثم أصلح إعدادات البريد.
