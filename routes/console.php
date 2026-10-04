<?php

use App\Services\DocumentWorkflow;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. On shared hosting one cron entry runs them all:
|   * * * * * php /path/to/wafiq/artisan schedule:run >> /dev/null 2>&1
*/

Artisan::command('wafiq:expire-documents', function (DocumentWorkflow $workflow) {
    $this->info($workflow->expireDue().' document(s) expired.');
})->purpose('Mark sent quotations past their validity date as expired');

// The client page also expires a document on the spot, so a missing cron never lets
// a client approve an out-of-date offer; this keeps lists and counts right.
Schedule::command('wafiq:expire-documents')->hourly();

// Queued emails (e.g. @mention emails). Shared hosting has no long-running worker, so the
// same cron entry empties the queue every minute.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->withoutOverlapping();
