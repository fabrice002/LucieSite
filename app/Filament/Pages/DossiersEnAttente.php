<?php

namespace App\Filament\Pages;

use App\Actions\DecideApplicationRetention;
use App\Actions\PurgeExpiredApplications;
use App\Models\Application;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * La file des dossiers arrivés à échéance.
 *
 * Deux issues, et deux seulement : conserver douze mois de plus, ou effacer.
 * Tant que rien n'est décidé, rien ne disparaît — et le dossier reste ici.
 *
 * Réservée au rôle admin : effacer des pièces d'identité n'est pas une décision
 * d'agent.
 */
class DossiersEnAttente extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Dossiers';

    protected static ?string $title = 'Dossiers arrivés à échéance';

    protected static ?string $navigationLabel = 'Arrivés à échéance';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.dossiers-en-attente';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('admin') ?? false;
    }

    /**
     * Le compteur affiché dans la navigation, pour qu'on ne puisse pas ignorer
     * la file sans la voir.
     */
    public static function getNavigationBadge(): ?string
    {
        $enAttente = PurgeExpiredApplications::enAttenteDeDecision()->count();

        return $enAttente > 0 ? (string) $enAttente : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getSubheading(): string
    {
        return 'Ces dossiers ont dépassé leur durée de conservation. Rien n\'a été supprimé : '
            .'les pièces sont intactes et le resteront tant que vous n\'aurez pas tranché.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(PurgeExpiredApplications::enAttenteDeDecision())
            ->emptyStateHeading('Aucun dossier n\'attend de décision')
            ->emptyStateDescription('Les dossiers arrivés au bout de leur durée de conservation apparaîtront ici.')
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->weight('medium')
                    ->searchable(),

                TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->searchable(['first_name', 'last_name']),

                TextColumn::make('status')
                    ->label('Statut du dossier')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color()),

                TextColumn::make('documents_count')
                    ->counts('documents')
                    ->label('Pièces')
                    ->alignCenter(),

                TextColumn::make('retention_due_at')
                    ->label('Échéance atteinte le')
                    ->dateTime('j M Y')
                    ->sortable(),

                TextColumn::make('retention_reminded_at')
                    ->label('Dernier rappel')
                    ->dateTime('j M Y')
                    ->placeholder('Aucun')
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('conserver')
                    ->label('Conserver 12 mois de plus')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Conserver ce dossier douze mois de plus')
                    ->modalDescription('Le dossier quitte cette file. Il y reviendra dans douze mois si rien ne bouge entre-temps.')
                    ->modalSubmitActionLabel('Conserver')
                    ->action(function (Application $record, DecideApplicationRetention $decision): void {
                        $decision->conserver($record);

                        Notification::make()
                            ->success()
                            ->title('Dossier '.$record->reference.' conservé')
                            ->body('Nouvelle échéance : '.$record->retention_due_at?->translatedFormat('j F Y').'.')
                            ->send();
                    }),

                Action::make('effacer')
                    ->label('Effacer définitivement')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Effacer définitivement ce dossier')
                    ->modalDescription(
                        'Le dossier et toutes ses pièces — passeport, diplômes, résultats de test — '
                        .'seront détruits sur le disque. Cette action est irréversible : ni corbeille, '
                        .'ni restauration possible. Seule une ligne du journal en gardera la trace.'
                    )
                    ->modalSubmitActionLabel('Effacer définitivement')
                    ->action(function (Application $record, DecideApplicationRetention $decision, PurgeExpiredApplications $purge): void {
                        $reference = $record->reference;

                        $decision->effacer($record, $purge);

                        Notification::make()
                            ->warning()
                            ->title('Dossier '.$reference.' effacé définitivement')
                            ->body('Les fichiers ont été détruits. La décision est consignée au journal.')
                            ->send();
                    }),
            ]);
    }

    /**
     * Rien de groupé ici : effacer des pièces d'identité en lot rendrait
     * l'erreur trop facile et trop coûteuse.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
