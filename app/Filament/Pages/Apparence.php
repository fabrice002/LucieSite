<?php

namespace App\Filament\Pages;

use App\Support\Couleur;
use App\Support\Palettes;
use App\Support\SiteSettingRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Réglage de l'identité visuelle du site, sans passer par un développeur.
 *
 * Une page plutôt qu'une ressource : il s'agit d'un formulaire unique, pas
 * d'une liste d'enregistrements à créer et supprimer.
 *
 * Réservée au rôle admin — un agent traite les dossiers, il ne redessine pas
 * le site.
 *
 * @property-read Schema $form
 */
class Apparence extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Contenu du site';

    protected static ?string $title = 'Apparence';

    protected static ?string $navigationLabel = 'Apparence';

    // En dernier du groupe : on règle l'apparence une fois, on écrit du contenu
    // tous les jours.
    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.apparence';

    /**
     * Les clés éditées ici, avec le type sous lequel elles sont enregistrées.
     */
    private const REGLAGES = [
        'couleur_principale',
        'couleur_secondaire',
        'couleur_accent',
        'couleur_texte_sur_principale',
        'police',
        'theme_sombre_actif',
        'logo_clair',
        'logo_sombre',
        'favicon',
        'palette',
    ];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('admin') ?? false;
    }

    public function getSubheading(): string
    {
        return 'Couleurs, logo et police du site public. Les modifications sont visibles immédiatement.';
    }

    public function mount(): void
    {
        $this->form->fill($this->valeursActuelles());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->sectionPalettes(),
                $this->sectionCouleurs(),
                $this->sectionMarque(),
                $this->sectionTypographie(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enregistrer')
                ->label('Enregistrer')
                ->icon('heroicon-o-check')
                ->action('enregistrer'),

            Action::make('regenerer_icones')
                ->label('Régénérer les icônes')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Régénérer les icônes du navigateur')
                ->modalDescription(
                    'Le favicon et l\'icône d\'écran d\'accueil seront redessinés à partir '
                    .'des couleurs de la marque. Les navigateurs conservent longtemps ces '
                    .'fichiers : videz le cache du vôtre pour voir le changement.'
                )
                ->modalSubmitActionLabel('Régénérer')
                ->action('regenererLesIcones'),

            Action::make('reinitialiser')
                ->label('Revenir à l\'apparence livrée')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Revenir à l\'apparence livrée')
                ->modalDescription('Toutes les couleurs et la police reprendront leurs valeurs d\'origine. Les logos téléversés sont conservés.')
                ->action('reinitialiser'),
        ];
    }

    /**
     * Enregistre les réglages. Le cache est vidé par l'observer.
     */
    public function enregistrer(): void
    {
        /** @var array<string, mixed> $donnees */
        $donnees = $this->form->getState();

        $reglages = app(SiteSettingRepository::class);

        foreach (self::REGLAGES as $cle) {
            $valeur = $donnees[$cle] ?? null;

            // Les couleurs sont normalisées pour que la CSS produite reste
            // prévisible, quelle que soit la façon dont elles ont été saisies.
            if (str_starts_with($cle, 'couleur_') && Couleur::estValide(is_string($valeur) ? $valeur : null)) {
                $valeur = Couleur::normaliser((string) $valeur);
            }

            $reglages->set($cle, $valeur);
        }

        $avertissement = $this->avertissementDeContraste($donnees);

        if ($avertissement !== null) {
            Notification::make()
                ->warning()
                ->title('Enregistré, mais le contraste est insuffisant')
                ->body($avertissement)
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Apparence enregistrée')
            ->body('Le site public l\'applique dès maintenant.')
            ->send();
    }

    /**
     * Rejoue la commande de génération des icônes.
     */
    public function regenererLesIcones(): void
    {
        $code = Artisan::call('ln:generate-icons');
        $sortie = trim(Artisan::output());

        if ($code !== 0) {
            Notification::make()
                ->danger()
                ->title('La génération a échoué')
                ->body($sortie !== '' ? $sortie : 'La commande ln:generate-icons a renvoyé une erreur.')
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Icônes régénérées')
            ->body($sortie !== '' ? $sortie : 'favicon.svg, favicon.ico et apple-touch-icon.png ont été réécrits.')
            ->send();
    }

    /**
     * Remet les couleurs et la police telles qu'elles sont livrées.
     */
    public function reinitialiser(): void
    {
        $reglages = app(SiteSettingRepository::class);

        foreach (['couleur_principale', 'couleur_secondaire', 'couleur_accent', 'couleur_texte_sur_principale', 'police', 'palette'] as $cle) {
            $reglages->set($cle, null);
        }

        $this->form->fill($this->valeursActuelles());

        Notification::make()
            ->success()
            ->title('Apparence d\'origine rétablie')
            ->send();
    }

    /**
     * Les valeurs à afficher : celles enregistrées, ou celles livrées.
     *
     * @return array<string, mixed>
     */
    private function valeursActuelles(): array
    {
        $valeurs = [];

        foreach (self::REGLAGES as $cle) {
            $valeurs[$cle] = setting($cle);
        }

        return $valeurs;
    }

    private function sectionPalettes(): Section
    {
        return Section::make('Palettes prêtes à l\'emploi')
            ->description('Le plus simple : choisissez une palette, elle remplit les couleurs ci-dessous. Toutes respectent le contraste minimum exigé pour la lisibilité.')
            ->schema([
                Radio::make('palette')
                    ->hiddenLabel()
                    ->options(Palettes::options())
                    ->descriptions(Palettes::descriptions())
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $couleurs = $state !== null ? Palettes::couleurs($state) : null;

                        if ($couleurs === null) {
                            return;
                        }

                        foreach ($couleurs as $cle => $valeur) {
                            $set($cle, $valeur);
                        }
                    }),
            ]);
    }

    private function sectionCouleurs(): Section
    {
        return Section::make('Couleurs')
            ->description('Pour aller plus loin que les palettes. La couleur principale sert aux boutons et aux liens ; l\'accent est réservé aux mises en avant ponctuelles.')
            ->schema([
                ColorPicker::make('couleur_principale')
                    ->label('Couleur principale')
                    ->required()
                    ->live(onBlur: true),

                ColorPicker::make('couleur_texte_sur_principale')
                    ->label('Texte posé sur la couleur principale')
                    ->helperText('Le plus souvent du blanc.')
                    ->required()
                    ->live(onBlur: true),

                ColorPicker::make('couleur_secondaire')
                    ->label('Couleur secondaire'),

                ColorPicker::make('couleur_accent')
                    ->label('Couleur d\'accent'),

                // L'avertissement est calculé en direct : la cliente le voit
                // avant d'enregistrer, pas après.
                Callout::make('Contraste insuffisant')
                    ->warning()
                    ->visible(fn (Get $get): bool => $this->avertissementDeContraste([
                        'couleur_principale' => $get('couleur_principale'),
                        'couleur_texte_sur_principale' => $get('couleur_texte_sur_principale'),
                    ]) !== null)
                    ->schema([
                        Text::make(fn (Get $get): string => (string) $this->avertissementDeContraste([
                            'couleur_principale' => $get('couleur_principale'),
                            'couleur_texte_sur_principale' => $get('couleur_texte_sur_principale'),
                        ])),
                    ]),
            ]);
    }

    private function sectionMarque(): Section
    {
        return Section::make('Logo et favicon')
            ->description('Sans logo téléversé, le monogramme « LN » est utilisé : il s\'adapte seul au thème clair comme sombre.')
            ->schema([
                FileUpload::make('logo_clair')
                    ->label('Logo')
                    ->helperText('Affiché sur fond clair. Format SVG ou PNG à fond transparent de préférence.')
                    ->image()
                    ->disk('public')
                    ->directory('marque')
                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(2048),

                FileUpload::make('logo_sombre')
                    ->label('Logo pour fond sombre')
                    ->helperText('Facultatif. Sans lui, le logo ci-dessus sert dans les deux thèmes.')
                    ->image()
                    ->disk('public')
                    ->directory('marque')
                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(2048),

                FileUpload::make('favicon')
                    ->label('Icône d\'onglet')
                    ->helperText('Facultatif. Sans elle, l\'icône générée à partir du monogramme est utilisée.')
                    ->image()
                    ->disk('public')
                    ->directory('marque')
                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/x-icon'])
                    ->maxSize(512),
            ]);
    }

    private function sectionTypographie(): Section
    {
        /** @var array<string, array{famille: string, description: string}> $polices */
        $polices = config('brand.polices', []);

        return Section::make('Typographie et thème')
            ->schema([
                Radio::make('police')
                    ->label('Police du site')
                    ->options(array_map(fn (array $police): string => $police['famille'], $polices))
                    ->descriptions(array_map(fn (array $police): string => $police['description'], $polices))
                    ->helperText('Toutes sont hébergées sur le site : rien n\'est demandé à un service extérieur, et un visiteur n\'en télécharge qu\'une.'),

                Toggle::make('theme_sombre_actif')
                    ->label('Proposer le thème sombre au public')
                    ->helperText('Désactivé, le site s\'affiche toujours en clair et le bouton de bascule disparaît.'),
            ]);
    }

    /**
     * Le message à afficher si le contraste est trop faible, sinon null.
     *
     * @param  array<string, mixed>  $donnees
     */
    private function avertissementDeContraste(array $donnees): ?string
    {
        $fond = $donnees['couleur_principale'] ?? null;
        $texte = $donnees['couleur_texte_sur_principale'] ?? null;

        if (! Couleur::estValide(is_string($fond) ? $fond : null)
            || ! Couleur::estValide(is_string($texte) ? $texte : null)) {
            return null;
        }

        $ratio = Couleur::contraste((string) $fond, (string) $texte);

        if ($ratio >= Couleur::CONTRASTE_MINIMUM) {
            return null;
        }

        return sprintf(
            'Le texte posé sur la couleur principale atteint un contraste de %s:1, '
            .'alors que %s:1 est le minimum pour rester lisible. '
            .'Les personnes malvoyantes, et tout le monde en plein soleil sur un téléphone, '
            .'auront du mal à lire vos boutons. Essayez un texte blanc sur une couleur plus foncée.',
            number_format($ratio, 1, ',', ' '),
            number_format(Couleur::CONTRASTE_MINIMUM, 1, ',', ' '),
        );
    }
}
