<?php

namespace App\Filament\Resources\SiteContents\Pages;

use App\Filament\Resources\SiteContents\SiteContentResource;
use Filament\Resources\Pages\ListRecords;

class ListSiteContents extends ListRecords
{
    protected static string $resource = SiteContentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
