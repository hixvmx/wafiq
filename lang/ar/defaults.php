<?php

/*
| Default texts for a new company. Every one of them can be changed in Settings.
| Message variables: {client_name} {number} {amount} {link} {valid_until} {due_date} {company}
*/

return [
    'documents' => [
        'quote' => [
            'terms' => "هذا العرض صالح حتى التاريخ المذكور فيه.\nيبدأ التنفيذ بعد الموافقة على العرض.",
            'notes' => '',
        ],
        'invoice' => [
            'terms' => 'يرجى السداد قبل تاريخ الاستحقاق.',
            'notes' => '',
        ],
    ],

    'templates' => [
        'quote' => [
            'whatsapp' => "مرحباً {client_name}، نرسل لك عرض السعر رقم {number} من {company} بقيمة {amount}.\nيمكنك مراجعته والموافقة عليه من هنا: {link}\nالعرض صالح حتى {valid_until}.",
            'email_subject' => 'عرض سعر {number} من {company}',
            'email_body' => "مرحباً {client_name}،\n\nنرسل لك عرض السعر رقم {number} بقيمة {amount}.\nيمكنك مراجعته والموافقة عليه من الرابط أدناه. العرض صالح حتى {valid_until}.\n\nمع التحية،\n{company}",
        ],
        'invoice' => [
            'whatsapp' => "مرحباً {client_name}، نرسل لك الفاتورة رقم {number} من {company} بقيمة {amount}.\nيمكنك الاطلاع عليها من هنا: {link}\nتاريخ الاستحقاق: {due_date}.",
            'email_subject' => 'فاتورة {number} من {company}',
            'email_body' => "مرحباً {client_name}،\n\nنرسل لك الفاتورة رقم {number} بقيمة {amount}.\nيمكنك الاطلاع عليها وتحميلها من الرابط أدناه. تاريخ الاستحقاق: {due_date}.\n\nمع التحية،\n{company}",
        ],
    ],
];
