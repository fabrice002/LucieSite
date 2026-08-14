<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Actions\BuildApplicationArchive;
use App\Filament\Resources\Applications\Actions\NotifyApplicantAction;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            NotifyApplicantAction::make(),

            Action::make('archive')
                ->label('Télécharger tous les documents')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('gray')
                ->visible(fn (Application $record): bool => $record->documents()->exists())
                ->action(function (Application $record, BuildApplicationArchive $archive): ?BinaryFileResponse {
                    try {
                        return $archive($record);
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Archive impossible')
                            ->body($exception->getMessage())
                            ->send();

                        return null;
                    }
                }),

            EditAction::make()->label('Traiter le dossier'),
        ];
    }
}
