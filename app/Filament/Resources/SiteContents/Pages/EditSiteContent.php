<?php

namespace App\Filament\Resources\SiteContents\Pages;

use App\Filament\Resources\SiteContents\SiteContentResource;
use App\Models\SiteContent;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditSiteContent extends EditRecord
{
    protected static string $resource = SiteContentResource::class;

    public function getTitle(): string
    {
        /** @var SiteContent $record */
        $record = $this->getRecord();

        return $record->label;
    }

    public function getSubheading(): string
    {
        return 'Les modifications sont visibles immédiatement sur le site public.';
    }

    public function getMaxContentWidth(): Width
    {
        return Width::FiveExtraLarge;
    }

    /**
     * Ni suppression ni duplication : un bloc ne doit jamais disparaître.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Textes enregistrés — le site public est déjà à jour.';
    }
}
