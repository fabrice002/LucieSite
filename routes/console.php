<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Les téléversements interrompus laissent des fichiers sur le disque privé.
Schedule::command('uploads:purge-temporary')->hourly();
