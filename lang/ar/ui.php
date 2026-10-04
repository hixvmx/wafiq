<?php

/*
|--------------------------------------------------------------------------
| Interface text
|--------------------------------------------------------------------------
|
| Every string shown in the app lives here. React pages read them through
| the useT() hook, e.g. t('nav.quotes'). Placeholders look like :name.
| To add English later, copy this file to lang/en/ui.php and translate it.
|
*/

return [
    'app' => [
        'tagline' => 'أرسل عرض السعر أو الفاتورة، تابع فتحها، واحصل على موافقة عميلك بضغطة.',
    ],

    'nav' => [
        'dashboard' => 'لوحة التحكم',
        'quotes' => 'عروض الأسعار',
        'invoices' => 'الفواتير',
        'clients' => 'العملاء',
        'items' => 'المنتجات والخدمات',
        'team' => 'الفريق',
        'settings' => 'الإعدادات',
        'notifications' => 'الإشعارات',
        'logout' => 'تسجيل الخروج',
        'menu' => 'القائمة',
    ],

    'common' => [
        'save' => 'حفظ التغييرات',
        'saving' => 'جارٍ الحفظ…',
        'cancel' => 'إلغاء',
        'delete' => 'حذف',
        'edit' => 'تعديل',
        'view' => 'عرض',
        'search' => 'بحث',
        'all' => 'الكل',
        'choose' => 'اختر…',
        'close' => 'إغلاق',
        'confirm' => 'تأكيد',
        'optional' => 'اختياري',
        'view_all' => 'عرض الكل',
        'processing' => 'جارٍ المعالجة…',
    ],

    'roles' => [
        'owner' => 'المالك',
        'admin' => 'مدير',
        'accountant' => 'محاسب',
        'sales' => 'مبيعات',
        'viewer' => 'مشاهد',
    ],

    'pagination' => [
        'previous' => 'السابق',
        'next' => 'التالي',
        'showing' => 'عرض :from–:to من :total',
    ],

    'errors' => [
        'back_home' => 'العودة إلى الرئيسية',
        '403' => [
            'title' => 'غير مسموح',
            'message' => 'ليست لديك صلاحية للوصول إلى هذه الصفحة.',
        ],
        '404' => [
            'title' => 'الصفحة غير موجودة',
            'message' => 'ربما تم حذف هذه الصفحة أو تغيير رابطها.',
        ],
        '419' => [
            'title' => 'انتهت صلاحية الصفحة',
            'message' => 'انتهت مدة الجلسة. أعد تحميل الصفحة وحاول مجدداً.',
        ],
        '429' => [
            'title' => 'محاولات كثيرة',
            'message' => 'انتظر قليلاً ثم حاول مجدداً.',
        ],
        '500' => [
            'title' => 'حدث خطأ غير متوقع',
            'message' => 'يرجى المحاولة لاحقاً.',
        ],
        '503' => [
            'title' => 'النظام تحت الصيانة',
            'message' => 'سنعود قريباً.',
        ],
    ],
];
