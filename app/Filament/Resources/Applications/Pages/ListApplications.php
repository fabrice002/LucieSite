<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    /**
     * Aucune action d'en-tête : un dossier ne se crée pas depuis le back-office.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
