<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// إذا كنت تستخدم Laravel 11 في routes/console.php:
Schedule::command('backup:clean')->daily()->at('03:00');
Schedule::command('backup:run')->daily()->at('03:30');
