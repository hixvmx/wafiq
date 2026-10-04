<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login & invitations
    |--------------------------------------------------------------------------
    */

    // Tests set this to true / false to skip the "is it installed?" check (null = really check).
    'force_installed' => null,

    // A magic login link works once, for this many minutes.
    'login_link_minutes' => 15,

    // Team invitations can be accepted for this many days (resend to renew).
    'invitation_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Tax presets (Settings → Taxes): country => standard VAT / GST rate in %.
    |--------------------------------------------------------------------------
    | Names come from lang/ar/ui.php (settings.taxes.presets).
    */

    'tax_presets' => [
        'SA' => 15,
        'AE' => 5,
        'BH' => 10,
        'OM' => 5,
        'EG' => 14,
        'JO' => 16,
        'MA' => 20,
        'DZ' => 19,
        'TN' => 19,
        'exempt' => 0,
    ],

];
