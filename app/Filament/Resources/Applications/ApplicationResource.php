<?php

namespace App\Filament\Resources\Applications;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\Applications\Pages\EditApplication;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Applications\Schemas\ApplicationForm;
use App\Filament\Resources\Applications\Schemas\ApplicationInfolist;
use App\Filament\Resources\Applications\Tables\ApplicationsTable;
use App\Models\Application;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Dossiers';

    protected static ?string $modelLabel = 'dossier';

    protected static ?string $pluralModelLabel = 'dossiers';

    protected static ?string $navigationLabel = 'Dossiers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema
    {
        return ApplicationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApplicationsTable::configure($table);
    }

    /**
     * Recherche globale : nom, e-mail et référence.
     *
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'first_name', 'last_name', 'email'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Application $record */
        return $record->reference.' — '.$record->full_name;
    }

    /**
     * Compteur des dossiers non encore traités, affiché dans la navigation.
     */
    public static function getNavigationBadge(): ?string
    {
        $nouveaux = Application::query()
            ->where('status', ApplicationStatus::Nouveau)
            ->count();

        return $nouveaux > 0 ? (string) $nouveaux : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getPages(): array
    {
        // Pas de page de création : un dossier naît du formulaire public.
        return [
            'index' => ListApplications::route('/'),
            'view' => ViewApplication::route('/{record}'),
            'edit' => EditApplication::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
