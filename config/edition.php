<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Edition
    |--------------------------------------------------------------------------
    |
    | "self_hosted": the Picalica edition. Exactly one company per install,
    | created by the installer; team members are invited.
    |
    | "saas": our hosted edition. Many companies, public signup, billing.
    | Its code lives in app/Saas, routes/saas.php and resources/js/Pages/Saas,
    | which the release script removes from the Picalica package.
    |
    */

    'name' => env('APP_EDITION', 'self_hosted'),

];
