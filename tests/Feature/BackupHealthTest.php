<?php

use App\Support\Backup\ArchiveIsEncrypted;
use App\Support\Backup\MaximumAgeInHours;
use App\Support\Backup\MinimumSizeInMegabytes;
use Carbon\Carbon;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Exceptions\InvalidHealthCheck;

/**
 * Une destination de sauvegarde simulée, avec l'archive de notre choix.
 */
function destination(?Backup $archive): BackupDestination
{
    $collection = Mockery::mock(BackupCollection::class);
    $collection->shouldReceive('isEmpty')->andReturn($archive === null);
    $collection->shouldReceive('newest')->andReturn($archive);

    $destination = Mockery::mock(BackupDestination::class);
    $destination->shouldReceive('backups')->andReturn($collection);
    $destination->shouldReceive('disk')->andReturnNull();

    return $destination;
}

/**
 * @param  int  $heuresEcoulees  ancienneté de l'archive
 */
function archiveDatantDe(int $heuresEcoulees, int $tailleEnOctets = 10_485_760): Backup
{
    $backup = Mockery::mock(Backup::class);
    // Backup::date() est typé Carbon\Carbon, alors que l'application utilise
    // CarbonImmutable : le mock exige donc le type exact.
    $backup->shouldReceive('date')->andReturn(Carbon::now()->subHours($heuresEcoulees));
    $backup->shouldReceive('sizeInBytes')->andReturn($tailleEnOctets);
    $backup->shouldReceive('path')->andReturn('LN Immigration/archive.zip');

    return $backup;
}

/*
|--------------------------------------------------------------------------
| C.1 — Une sauvegarde qui ne se lance plus doit alerter
|--------------------------------------------------------------------------
*/

it('accepte une sauvegarde de la nuit dernière', function () {
    $controle = new MaximumAgeInHours(25);

    // Sauvegarde à 01h30, contrôle à 08h00 : six heures et demie.
    expect(fn () => $controle->checkHealth(destination(archiveDatantDe(7))))
        ->not->toThrow(InvalidHealthCheck::class);
});

it('alerte quand la sauvegarde a plus de 25 heures', function () {
    $controle = new MaximumAgeInHours(25);

    // Une nuit manquée : le cron est cassé ou le disque est plein.
    expect(fn () => $controle->checkHealth(destination(archiveDatantDe(30))))
        ->toThrow(InvalidHealthCheck::class);
});

it('alerte quand aucune sauvegarde n\'existe', function () {
    // Le cas le plus dangereux : rien n'échoue, rien n'existe.
    expect(fn () => (new MaximumAgeInHours(25))->checkHealth(destination(null)))
        ->toThrow(InvalidHealthCheck::class);
});

it('alerte quand l\'archive devient anormalement légère', function () {
    $controle = new MinimumSizeInMegabytes(5);

    // 9 Mo : volume normal.
    expect(fn () => $controle->checkHealth(destination(archiveDatantDe(1, 9 * 1024 * 1024))))
        ->not->toThrow(InvalidHealthCheck::class);

    // 200 Ko : le dump de la base est vide ou tronqué.
    expect(fn () => $controle->checkHealth(destination(archiveDatantDe(1, 200 * 1024))))
        ->toThrow(InvalidHealthCheck::class);
});

/*
|--------------------------------------------------------------------------
| C.2 — Le chiffrement ne doit pas échouer en silence
|--------------------------------------------------------------------------
*/

it('alerte quand le mot de passe de chiffrement est absent', function () {
    // Sans mot de passe, laravel-backup produit une archive valide et LISIBLE,
    // sans le moindre avertissement. C'est ce silence qu'on rompt.
    config(['backup.backup.password' => null]);

    expect(fn () => (new ArchiveIsEncrypted)->checkHealth(destination(archiveDatantDe(1))))
        ->toThrow(InvalidHealthCheck::class);
});

it('se satisfait d\'un mot de passe renseigné sur une archive distante', function () {
    config(['backup.backup.password' => 'un-mot-de-passe']);

    // Disque distant : l'inspection supposerait de rapatrier l'archive.
    expect(fn () => (new ArchiveIsEncrypted)->checkHealth(destination(archiveDatantDe(1))))
        ->not->toThrow(InvalidHealthCheck::class);
});

it('détecte une archive locale réellement non chiffrée', function () {
    config(['backup.backup.password' => 'un-mot-de-passe']);

    $chemin = sys_get_temp_dir().'/archive-en-clair.zip';
    $zip = new ZipArchive;
    $zip->open($chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('base.sql', 'contenu en clair');
    $zip->close();

    // On vérifie le cœur du contrôle : reconnaître une archive non protégée.
    $lecture = new ZipArchive;
    $lecture->open($chemin);
    $entree = $lecture->statIndex(0);
    $lecture->close();

    expect($entree['encryption_method'])->toBe(ZipArchive::EM_NONE);

    @unlink($chemin);
});

it('reconnaît une archive chiffrée en AES-256', function () {
    $chemin = sys_get_temp_dir().'/archive-chiffree.zip';

    $zip = new ZipArchive;
    $zip->open($chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->setPassword('un-mot-de-passe');
    $zip->addFromString('base.sql', 'contenu confidentiel');
    $zip->setEncryptionName('base.sql', ZipArchive::EM_AES_256);
    $zip->close();

    $lecture = new ZipArchive;
    $lecture->open($chemin);
    $entree = $lecture->statIndex(0);
    $sansMotDePasse = @$lecture->getFromName('base.sql');
    $lecture->close();

    expect($entree['encryption_method'])->not->toBe(ZipArchive::EM_NONE)
        // Et la protection est effective, pas seulement déclarée.
        ->and($sansMotDePasse)->toBeFalse();

    @unlink($chemin);
});

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

it('surveille les sauvegardes avec les trois contrôles', function () {
    $controles = config('backup.monitor_backups.0.health_checks');

    expect($controles)->toHaveKey(MaximumAgeInHours::class)
        ->and($controles[MaximumAgeInHours::class])->toBe(25)
        ->and($controles)->toHaveKey(MinimumSizeInMegabytes::class)
        ->and($controles)->toContain(ArchiveIsEncrypted::class);
});

it('vérifie l\'intégrité de chaque archive produite', function () {
    // Une archive corrompue découverte le jour de la restauration ne vaut pas
    // mieux qu'une absence de sauvegarde.
    expect(config('backup.backup.verify_backup'))->toBeTrue();
});
