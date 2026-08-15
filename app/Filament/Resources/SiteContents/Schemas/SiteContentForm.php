<?php

namespace App\Filament\Resources\SiteContents\Schemas;

use App\Filament\Forms\ContentImage;
use App\Models\SiteContent;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Formulaire généré dynamiquement à partir des clés du bloc.
 *
 * L'administratrice ne voit jamais de JSON : un champ par clé, réparti en
 * sections comme dans les autres écrans du back-office. Une page d'accueil
 * compte une trentaine de textes ; les livrer en liste à plat obligerait à
 * relire chaque libellé pour retrouver celui qu'on cherche.
 */
class SiteContentForm
{
    /** Au-delà de cette longueur, un texte mérite une zone multiligne. */
    private const SEUIL_TEXTAREA = 90;

    /**
     * Regroupement des clés par préfixe, dans l'ordre d'affichage.
     *
     * Chaque entrée : préfixe => [titre de section, explication].
     *
     * Une clé qui ne correspond à aucun préfixe atterrit dans « Contenu de la
     * page » : ajouter une clé au seeder ne casse donc rien, elle apparaît
     * simplement dans la section générale.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const SECTIONS = [
        'hero_' => ['Bannière principale', 'Le premier écran, avant tout défilement. C\'est ce que voit un candidat qui arrive.'],
        'section_' => ['Titres des sections', 'Les intitulés qui séparent les grands blocs de la page.'],
        'etape_' => ['Étapes du processus', 'Laissez un titre vide pour retirer l\'étape correspondante. Vous pouvez en ajouter.'],
        'valeur_' => ['Nos valeurs', 'Une carte par valeur renseignée. Celles laissées vides n\'apparaissent pas.'],
        'element_' => ['Chiffres de réassurance', 'N\'indiquez que des données réelles et vérifiables. Un emplacement vide ne s\'affiche pas.'],
        'statut_' => ['Statut professionnel', 'À quel titre le cabinet intervient. N\'inventez ni agrément, ni numéro d\'enregistrement.'],
        'recherche_' => ['Champ de recherche', 'Les libellés du filtre affiché au-dessus des questions.'],
        'modele_' => ['Modèles de message', 'Textes proposés lors d\'un changement de statut. Ils restent modifiables au cas par cas.'],
        'nav_' => ['Navigation', 'Les libellés du menu, repris dans l\'en-tête et le pied de page.'],
        'footer_' => ['Pied de page', 'Coordonnées et mentions. Un champ laissé vide n\'affiche rien du tout.'],
        'cta_' => ['Appel à l\'action', 'Le bandeau affiché en bas de la page.'],
        'meta_' => ['Référencement', 'Ce que lisent les moteurs de recherche et les réseaux sociaux. Invisible sur la page.'],
    ];

    /** Section d'accueil des clés qui ne correspondent à aucun préfixe. */
    private const SECTION_GENERALE = 'Contenu de la page';

    public static function configure(Schema $schema): Schema
    {
        return $schema->components(fn (?SiteContent $record): array => self::sections($record));
    }

    /**
     * Répartit les clés du bloc en sections.
     *
     * @return list<Section>
     */
    private static function sections(?SiteContent $record): array
    {
        if ($record === null) {
            return [];
        }

        /** @var array<string, list<TextInput|Textarea|RichEditor|FileUpload>> $parSection */
        $parSection = [];

        foreach ($record->content as $cle => $valeur) {
            $parSection[self::sectionDe($cle)][] = self::champ($cle, (string) $valeur);
        }

        $sections = [];

        // La section générale d'abord : elle porte le titre et l'introduction
        // de la page, ce qu'on vient modifier le plus souvent.
        foreach (self::ordreDesSections() as $titre) {
            if (! isset($parSection[$titre])) {
                continue;
            }

            $sections[] = Section::make($titre)
                ->description(self::descriptionDe($titre))
                ->schema($parSection[$titre])
                ->collapsible()
                ->persistCollapsed();
        }

        return $sections;
    }

    /**
     * L'ordre d'affichage des sections.
     *
     * @return list<string>
     */
    private static function ordreDesSections(): array
    {
        $titres = [self::SECTION_GENERALE];

        foreach (self::SECTIONS as [$titre]) {
            $titres[] = $titre;
        }

        return $titres;
    }

    /**
     * La section à laquelle appartient une clé.
     */
    private static function sectionDe(string $cle): string
    {
        foreach (self::SECTIONS as $prefixe => [$titre]) {
            if (str_starts_with($cle, $prefixe)) {
                return $titre;
            }
        }

        return self::SECTION_GENERALE;
    }

    private static function descriptionDe(string $titre): string
    {
        foreach (self::SECTIONS as [$nom, $description]) {
            if ($nom === $titre) {
                return $description;
            }
        }

        return 'Le titre et le texte d\'introduction de la page.';
    }

    private static function champ(string $cle, string $valeur): TextInput|Textarea|RichEditor|FileUpload
    {
        $chemin = 'content.'.$cle;
        $libelle = self::libelle($cle);

        // Les clés en _image reçoivent un vrai téléversement.
        //
        // Sans cela, la cliente aurait devant elle un champ texte attendant un
        // chemin de stockage : autant dire une image qu'elle ne pourra jamais
        // mettre. Mêmes règles que partout ailleurs — disque public, 1600 px,
        // 4 Mo — via ContentImage.
        if (self::estImage($cle)) {
            return ContentImage::upload($chemin, 'images/pages')
                ->label($libelle)
                ->columnSpanFull();
        }

        // Les clés en _html accueillent du texte mis en forme.
        if (str_ends_with($cle, '_html')) {
            return RichEditor::make($chemin)
                ->label($libelle)
                ->helperText($cle)
                ->columnSpanFull();
        }

        if (self::estLong($cle, $valeur)) {
            return Textarea::make($chemin)
                ->label($libelle)
                ->helperText($cle)
                ->rows(4)
                ->maxLength(5000)
                ->columnSpanFull();
        }

        return TextInput::make($chemin)
            ->label($libelle)
            ->helperText($cle)
            ->maxLength(255)
            ->columnSpanFull();
    }

    /**
     * La clé désigne-t-elle un fichier image, et non un texte ?
     *
     * « _image_alt » est exclu : c'est bien du texte, celui que liront les
     * lecteurs d'écran.
     */
    private static function estImage(string $cle): bool
    {
        return str_ends_with($cle, '_image') || $cle === 'image';
    }

    /**
     * Decide whether a value deserves a multi-line field.
     */
    private static function estLong(string $cle, string $valeur): bool
    {
        if (mb_strlen($valeur) > self::SEUIL_TEXTAREA) {
            return true;
        }

        // Un placeholder est court aujourd'hui mais accueillera un paragraphe.
        return (bool) preg_match(
            '/(texte|description|intro|paragraphe|message|adresse|mention|baseline|sous_titre|aide)/',
            $cle,
        );
    }

    /**
     * Un libellé lisible pour une clé.
     *
     * Quelques tournures reviennent partout et méritent mieux qu'un mot à mot :
     * « Hero sous titre » devient « Sous-titre », dans une section déjà
     * intitulée « Bannière principale ». La clé brute reste sous le champ, pour
     * qui doit la retrouver dans le code.
     *
     * @var array<string, string>
     */
    private const LIBELLES = [
        'titre' => 'Titre',
        'sous_titre' => 'Sous-titre',
        'introduction' => 'Introduction',
        'texte' => 'Texte',
        'html' => 'Contenu',
        'bouton' => 'Libellé du bouton',
        'bouton_secondaire' => 'Libellé du bouton secondaire',
        'image' => 'Image',
        'image_alt' => 'Description de l\'image',
        'label' => 'Libellé',
        'placeholder' => 'Texte d\'invite',
        'mention' => 'Mention',
        'vide' => 'Message quand la liste est vide',
        'meta_titre' => 'Titre pour les moteurs de recherche',
        'meta_description' => 'Description pour les moteurs de recherche',
    ];

    private static function libelle(string $cle): string
    {
        if (isset(self::LIBELLES[$cle])) {
            return self::LIBELLES[$cle];
        }

        // Dans une section, le préfixe est déjà porté par son titre : on ne le
        // répète pas dans chaque libellé.
        foreach (self::SECTIONS as $prefixe => $_) {
            if (! str_starts_with($cle, $prefixe)) {
                continue;
            }

            $reste = Str::after($cle, $prefixe);

            return self::LIBELLES[$reste] ?? self::humaniser($reste);
        }

        return self::humaniser($cle);
    }

    /**
     * Turn "section_services_titre" into "Section services titre".
     */
    private static function humaniser(string $cle): string
    {
        return Str::of($cle)
            ->replace('_', ' ')
            ->trim()
            ->ucfirst()
            ->value();
    }
}
