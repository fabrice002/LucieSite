<?php

namespace App\Filament\Resources\SiteContents;

use App\Filament\Resources\SiteContents\Pages\EditSiteContent;
use App\Filament\Resources\SiteContents\Pages\ListSiteContents;
use App\Filament\Resources\SiteContents\Schemas\SiteContentForm;
use App\Filament\Resources\SiteContents\Tables\SiteContentsTable;
use App\Models\SiteContent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SiteContentResource extends Resource
{
    protected static ?string $model = SiteContent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    // Même groupe que les services, la FAQ et les blocs de page : tout ce qui
    // s'affiche sur le site public se règle au même endroit. Séparer « textes »
    // et « contenu » n'avait de sens que pour qui connaît le schéma de la base.
    protected static string|UnitEnum|null $navigationGroup = 'Contenu du site';

    protected static ?string $modelLabel = 'bloc de textes';

    protected static ?string $pluralModelLabel = 'textes du site';

    protected static ?string $navigationLabel = 'Textes des pages';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return SiteContentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiteContentsTable::configure($table);
    }

    /**
     * Les blocs ne se créent pas depuis l'interface : leurs clés sont
     * référencées dans les vues et proviennent du seeder.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteContents::route('/'),
            'edit' => EditSiteContent::route('/{record}/edit'),
        ];
    }
}
