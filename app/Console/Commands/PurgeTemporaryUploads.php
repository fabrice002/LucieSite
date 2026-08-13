<?php

namespace App\Console\Commands;

use App\Support\TemporaryUploadStorage;
use Illuminate\Console\Command;

class PurgeTemporaryUploads extends Command
{
    protected $signature = 'uploads:purge-temporary';

    protected $description = 'Supprime les téléversements abandonnés ou déjà consommés';

    public function handle(TemporaryUploadStorage $storage): int
    {
        $removed = $storage->purgeStale();

        $this->info($removed === 0
            ? 'Aucun téléversement à supprimer.'
            : "{$removed} téléversement(s) supprimé(s).");

        return self::SUCCESS;
    }
}
