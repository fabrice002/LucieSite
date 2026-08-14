<?php

namespace Database\Seeders;

use App\Enums\SectionType;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\PageSection;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Contenu éditorial de départ : services, questions fréquentes, blocs de page.
 *
 * Comme SiteContentSeeder, il n'écrase jamais ce qui existe : le relancer après
 * une modification faite dans le back-office est sans effet.
 *
 * Le contenu rédactionnel reste à écrire par la cliente. Aucune promesse de
 * résultat, aucun taux de réussite, aucun chiffre inventé : ce secteur attire
 * la fraude, et une statistique fabriquée dessert le cabinet autant que les
 * candidats.
 */
class ContentSeeder extends Seeder
{
    private const TODO = '[À COMPLÉTER PAR LA CLIENTE]';

    public function run(): void
    {
        $this->services();
        $this->faq();
        $this->sections();
    }

    /**
     * Les programmes d'immigration les plus courants vers le Canada.
     *
     * Non publiés : la cliente rédige le contenu, puis publie.
     */
    private function services(): void
    {
        $services = [
            ['Entrée Express', 'Le programme fédéral de résidence permanente pour les travailleurs qualifiés.', 'Le plus demandé'],
            ['PSTQ — Québec', 'Le Programme de sélection des travailleurs qualifiés du Québec.', null],
            ['Permis d\'études', 'L\'autorisation d\'étudier dans un établissement canadien désigné.', null],
            ['Permis de travail', 'Les autorisations de travail temporaire, avec ou sans étude d\'impact.', null],
            ['Regroupement familial', 'Le parrainage d\'un conjoint, d\'un enfant ou d\'un parent.', null],
            ['Visa visiteur', 'Le séjour temporaire pour tourisme, visite familiale ou affaires.', null],
        ];

        foreach ($services as $ordre => [$titre, $resume, $mention]) {
            Service::query()->firstOrCreate(
                ['slug' => Str::slug($titre)],
                [
                    'title' => $titre,
                    'summary' => $resume,
                    'body' => '<p>'.self::TODO.' — décrivez ici les conditions d\'admissibilité, '
                        .'les étapes, les délais indicatifs et ce que le cabinet prend en charge.</p>',
                    'highlight' => $mention,
                    'sort_order' => $ordre,
                    'is_published' => false,
                ],
            );
        }
    }

    /**
     * Trois thèmes et une douzaine de questions courantes.
     *
     * Aucun plafond n'est codé : la cliente en ajoute autant qu'elle veut.
     */
    private function faq(): void
    {
        $themes = [
            'Avant de déposer' => [
                'Comment savoir si je suis admissible ?',
                'Quels documents dois-je préparer ?',
                'Faut-il parler anglais ou français ?',
                'Combien de temps prend une demande ?',
            ],
            'Le dépôt de mon dossier' => [
                'Dans quel format envoyer mes documents ?',
                'Que faire si ma connexion coupe pendant l\'envoi ?',
                'Puis-je compléter mon dossier après l\'avoir envoyé ?',
                'Mes documents sont-ils en sécurité ?',
            ],
            'Après le dépôt' => [
                'Comment suivre l\'avancement de mon dossier ?',
                'Que signifie chaque statut ?',
                'Combien de temps mes données sont-elles conservées ?',
                'Comment demander la suppression de mon dossier ?',
            ],
        ];

        $ordre = 0;

        foreach ($themes as $theme => $questions) {
            $categorie = FaqCategory::query()->firstOrCreate(
                ['slug' => Str::slug($theme)],
                ['name' => $theme, 'sort_order' => $ordre++, 'is_published' => true],
            );

            foreach ($questions as $rang => $question) {
                Faq::query()->firstOrCreate(
                    ['faq_category_id' => $categorie->getKey(), 'question' => $question],
                    [
                        'answer' => '<p>'.self::TODO.'</p>',
                        'sort_order' => $rang,
                        'is_published' => true,
                    ],
                );
            }
        }
    }

    /**
     * Une composition de départ pour l'accueil et la page À propos.
     */
    private function sections(): void
    {
        $blocs = [
            ['accueil', SectionType::Etapes, [
                'titre' => 'Comment ça se passe',
                'introduction' => self::TODO,
                'etapes' => [
                    ['titre' => 'Vous déposez votre dossier', 'description' => self::TODO],
                    ['titre' => 'Nous l\'étudions', 'description' => self::TODO],
                    ['titre' => 'Nous vous accompagnons', 'description' => self::TODO],
                ],
            ]],

            // Livré vide : seules des données réelles ont leur place ici.
            ['accueil', SectionType::Chiffres, [
                'titre' => 'En quelques chiffres',
                'chiffres' => [],
            ]],

            ['a-propos', SectionType::TexteImage, [
                'titre' => 'Notre approche',
                'texte' => '<p>'.self::TODO.'</p>',
                'position_image' => 'droite',
            ]],

            ['a-propos', SectionType::Citation, [
                'texte' => self::TODO,
                'auteur' => self::TODO,
                'fonction' => self::TODO,
            ]],
        ];

        foreach ($blocs as $ordre => [$page, $type, $data]) {
            $existe = PageSection::query()
                ->where('page', $page)
                ->where('type', $type->value)
                ->exists();

            if ($existe) {
                continue;
            }

            PageSection::query()->create([
                'page' => $page,
                'type' => $type->value,
                'sort_order' => $ordre,
                // Les blocs de départ ne sont pas publiés : ils contiennent des
                // placeholders, qui n'ont rien à faire en ligne.
                'is_published' => false,
                'data' => $data,
            ]);
        }
    }
}
