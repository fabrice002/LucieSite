<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
|
| Le serveur doit exécuter le planificateur toutes les minutes :
|   * * * * * cd /chemin/du/site && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Les téléversements interrompus laissent des fichiers sur le disque privé.
Schedule::command('uploads:purge-temporary')->hourly();

// Effacement définitif des dossiers supprimés depuis plus de 90 jours,
// fichiers compris. Passe la nuit, quand personne ne consulte le site.
Schedule::command('ln:purge-applications')->dailyAt('03:30');

// Sauvegarde quotidienne vers le stockage distant, puis nettoyage des
// anciennes archives selon la politique de rétention de config/backup.php.
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30');
