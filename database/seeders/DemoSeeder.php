<?php

namespace Database\Seeders;

use App\Actions\PurgeExpiredApplications;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Enums\SectionType;
use App\Models\Application;
use App\Models\ApplicationUpdate;
use App\Models\Document;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\SiteContent;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\Palettes;
use App\Support\SiteSettingRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Jeu de démonstration : le site rempli comme il le sera une fois en service.
 *
 * Sert à voir et à tester l'ensemble — sur un ordinateur comme sur un téléphone
 * Android — sans attendre que la cliente ait tout rédigé : toutes les pages
 * publiées, des dossiers dans chaque statut, la file de conservation garnie.
 *
 * Deux garde-fous, et ils comptent :
 *
 *   1. Le seeder refuse de tourner en production. Ce secteur attire la fraude ;
 *      un faux témoignage ou un chiffre inventé mis en ligne serait un mensonge
 *      envers des candidats déjà très exposés.
 *   2. Chaque enregistrement porte la marque « DÉMO ». Aucun contenu de
 *      démonstration ne peut être confondu avec du contenu réel, ni oublié en
 *      ligne sans que cela saute aux yeux.
 *
 * Tout se retire d'un coup : « php artisan ln:demo --purge ».
 */
class DemoSeeder extends Seeder
{
    /**
     * La marque apposée sur tout ce que ce seeder crée.
     */
    public const MARQUE = 'DÉMO';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DemoSeeder refuse de tourner en production. Il crée des témoignages et des '
                .'chiffres fictifs, qui n\'ont rien à faire sur un site en service.'
            );
        }

        $this->comptes();
        $this->apparence();
        $this->textes();
        $this->services();
        $this->faq();
        $this->equipe();
        $this->temoignages();
        $this->blocs();
        $this->dossiers();
    }

    /*
    |--------------------------------------------------------------------------
    | Comptes du personnel
    |--------------------------------------------------------------------------
    */

    private function comptes(): void
    {
        $this->call(RoleSeeder::class);

        foreach ([['admin', 'Administratrice'], ['agent', 'Agent']] as [$role, $libelle]) {
            $compte = User::query()->firstOrCreate(
                ['email' => $role.'@demo.test'],
                [
                    'name' => $libelle.' '.self::MARQUE,
                    'password' => bcrypt('motdepasse'),
                    'email_verified_at' => now(),
                ],
            );

            if (! $compte->hasRole($role)) {
                $compte->assignRole($role);
            }
        }

        $this->command->line('  Comptes : admin@demo.test / agent@demo.test — mot de passe « motdepasse »');
    }

    /*
    |--------------------------------------------------------------------------
    | Apparence
    |--------------------------------------------------------------------------
    */

    private function apparence(): void
    {
        /** @var array<string, string> $palette */
        $palette = Palettes::couleurs('bleu_institutionnel') ?? [];

        app(SiteSettingRepository::class)->setMany($palette + [
            'palette' => 'bleu_institutionnel',
            'police' => 'instrument-sans',
            'theme_sombre_actif' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Textes : chaque placeholder est remplacé par un texte plausible
    |--------------------------------------------------------------------------
    */

    private function textes(): void
    {
        $this->call(SiteContentSeeder::class);

        foreach ($this->blocsDeTextes() as $cle => $valeurs) {
            $bloc = SiteContent::query()->where('key', $cle)->where('locale', 'fr')->first();

            if ($bloc === null) {
                continue;
            }

            $bloc->update(['content' => array_merge($bloc->content, $valeurs)]);
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function blocsDeTextes(): array
    {
        $marque = self::MARQUE;

        return [
            'global' => [
                'baseline' => "Accompagnement à l'immigration au Canada, depuis Douala. [{$marque}]",
                'footer_adresse' => "12 rue de la Réunification, Akwa, Douala — Cameroun [{$marque}]",
                'footer_telephone' => '+237 6 99 00 00 00',
                'footer_whatsapp' => '+237 6 99 00 00 00',
                'footer_email' => 'contact@demo.test',
                'footer_horaires' => 'Du lundi au vendredi, 8h – 17h (GMT+1)',
                'statut_professionnel' => "Cabinet de conseil en immigration. [{$marque}] — le statut réel et le "
                    ."numéro d'enregistrement doivent être renseignés par la cliente.",
            ],

            'accueil' => [
                'meta_description' => "Cabinet d'accompagnement à l'immigration au Canada. [{$marque}]",
                'hero_titre' => "Votre projet d'immigration au Canada commence ici",
                'hero_sous_titre' => 'Nous étudions votre profil, préparons votre dossier et vous accompagnons '
                    ."jusqu'à la décision. Un interlocuteur unique, du premier échange au départ.",
                'hero_mention' => "Première évaluation de profil sans engagement. [{$marque}]",
                'section_etapes_intro' => "Quatre étapes, annoncées à l'avance. Vous savez à tout moment où en est votre dossier.",
                'etape_1_texte' => 'Un entretien et une lecture attentive de votre parcours, pour identifier les '
                    .'programmes auxquels vous êtes réellement admissible.',
                'etape_2_texte' => "Vous téléversez vos pièces depuis votre téléphone. L'envoi reprend tout seul si le réseau coupe.",
                'etape_3_texte' => 'Nous vérifions chaque document, complétons les formulaires et déposons la demande.',
                'etape_4_texte' => "Vous suivez l'avancement en ligne avec votre référence, et nous vous prévenons à chaque étape.",
                'section_services_intro' => 'Chaque programme a ses conditions et ses délais. Voici ceux que nous traitons.',
                'cta_titre' => 'Prêt à faire évaluer votre dossier ?',
                'cta_texte' => 'Déposez vos pièces en quelques minutes. Nous revenons vers vous sous 72 heures ouvrées.',
            ],

            // Chiffres de démonstration, explicitement marqués. Les vrais sont à
            // saisir par la cliente — et rien d'autre.
            'reassurance' => [
                'element_1_valeur' => "8 [{$marque}]",
                'element_2_valeur' => "450 [{$marque}]",
                'element_3_valeur' => "6 [{$marque}]",
                'element_4_valeur' => "À renseigner [{$marque}]",
            ],

            'services' => [
                'meta_description' => "Les programmes d'immigration accompagnés par le cabinet. [{$marque}]",
                'introduction' => 'Nous accompagnons les programmes fédéraux et québécois les plus demandés depuis '
                    ."l'Afrique centrale.",
                'cta_titre' => 'Vous hésitez entre plusieurs programmes ?',
            ],

            'a_propos' => [
                'meta_description' => "Le cabinet, son équipe et sa façon de travailler. [{$marque}]",
                'introduction' => 'Un cabinet camerounais, installé à Douala, qui accompagne des candidats vers le Canada.',
                'histoire_texte' => "Le cabinet est né du constat qu'un dossier d'immigration se perd rarement sur le "
                    .'fond, mais très souvent sur la forme : une pièce manquante, une traduction non conforme, un '
                    ."délai dépassé. [{$marque}]",
                'mission_texte' => "Rendre lisible une procédure qui ne l'est pas, et n'accepter que les dossiers que "
                    ."nous pensons pouvoir défendre. [{$marque}]",
                'valeur_1_titre' => 'Nous disons non',
                'valeur_1_texte' => 'Si votre profil ne correspond à aucun programme, nous vous le disons dès le premier entretien.',
                'valeur_2_titre' => 'Aucune promesse de résultat',
                'valeur_2_texte' => 'La décision appartient aux autorités canadiennes. Personne ne peut la garantir — '
                    ."méfiez-vous de qui l'affirme.",
                'valeur_3_titre' => 'Vos documents restent les vôtres',
                'valeur_3_texte' => 'Vos pièces sont stockées à part, accessibles aux seuls membres habilités, et '
                    .'effaçables sur simple demande.',
                'equipe_texte' => 'Une équipe réduite, que vous aurez au téléphone.',
                'statut_texte' => "Cabinet de conseil en immigration établi à Douala. [{$marque}] — texte à remplacer "
                    .'par le statut réel.',
                'statut_numero' => "R000000 [{$marque}]",
                'statut_verification' => "Vous pouvez vérifier ce numéro auprès de l'autorité compétente avant de nous "
                    .'confier quoi que ce soit.',
                'cta_titre' => 'Parlons de votre projet',
            ],

            'temoignages' => [
                'meta_description' => "Retours de candidats accompagnés par le cabinet. [{$marque}]",
                'introduction' => "Recueillis avec l'accord écrit des personnes concernées.",
                'cta_titre' => 'Votre parcours peut être le prochain',
            ],

            'depot' => [
                'meta_description' => "Déposer son dossier en ligne, depuis un téléphone. [{$marque}]",
                'introduction' => 'Remplissez les champs, joignez vos pièces, et vous recevrez une référence de '
                    ."suivi. L'envoi reprend tout seul si votre connexion coupe.",
                'mention_donnees' => 'Vos documents sont stockés sur un espace privé et ne sont jamais accessibles '
                    ."par une adresse publique. Vous pouvez en demander l'effacement à tout moment.",
            ],

            'confirmation' => [
                'suite' => 'Nous examinons votre dossier et revenons vers vous sous 72 heures ouvrées. Conservez '
                    .'votre référence : elle seule permet de suivre votre demande.',
            ],

            'suivi' => [
                'meta_description' => "Suivre l'avancement de son dossier avec sa référence. [{$marque}]",
                'aide_contact' => 'Référence égarée ? Écrivez-nous à contact@demo.test en précisant votre nom et '
                    ."la date approximative de votre dépôt. [{$marque}]",
            ],

            'faq' => [
                'meta_description' => "Réponses aux questions les plus fréquentes. [{$marque}]",
                'introduction' => "Une question qui n'y figure pas ? Écrivez-nous, nous l'ajouterons.",
            ],

            'contact' => [
                'meta_description' => "Joindre le cabinet à Douala. [{$marque}]",
                'introduction' => 'Par téléphone, WhatsApp ou e-mail. Nous répondons sous 72 heures ouvrées.',
                'adresse_texte' => "12 rue de la Réunification, Akwa, Douala — Cameroun [{$marque}]",
                'telephone_texte' => '+237 6 99 00 00 00 — également joignable sur WhatsApp',
                'email_texte' => 'contact@demo.test',
                'horaires_texte' => 'Du lundi au vendredi, 8h – 17h (GMT+1). Fermé les jours fériés.',
                'depot_texte' => 'Pour faire étudier votre profil, le plus simple reste de déposer votre dossier '
                    .'en ligne : vous recevrez une référence de suivi immédiatement.',
            ],

            // Mentions légales et politique de confidentialité : volontairement
            // laissées en placeholder, même en démonstration.
            //
            // Raison sociale, immatriculation, hébergeur, autorité de contrôle :
            // ce sont des mentions juridiques. En inventer, fût-ce pour une
            // démonstration, c'est risquer qu'elles partent en ligne telles
            // quelles. Seule la cliente peut les fournir.
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    */

    private function services(): void
    {
        $services = [
            ['Entrée Express', 'Le programme fédéral de résidence permanente pour les travailleurs qualifiés.', 'Le plus demandé'],
            ['PSTQ — Québec', 'Le Programme de sélection des travailleurs qualifiés du Québec.', null],
            ["Permis d'études", "L'autorisation d'étudier dans un établissement canadien désigné.", null],
            ['Permis de travail', "Les autorisations de travail temporaire, avec ou sans étude d'impact.", null],
            ['Regroupement familial', "Le parrainage d'un conjoint, d'un enfant ou d'un parent.", null],
            ['Visa visiteur', 'Le séjour temporaire pour tourisme, visite familiale ou affaires.', 'Délai court'],
        ];

        foreach ($services as $ordre => [$titre, $resume, $mention]) {
            Service::query()->updateOrCreate(
                ['slug' => Str::slug($titre)],
                [
                    'title' => $titre,
                    'summary' => $resume,
                    'body' => "<h2>Conditions d'admissibilité</h2>"
                        .'<p>Contenu de démonstration ['.self::MARQUE.']. Décrivez ici les conditions, les pièces '
                        .'attendues et les délais indicatifs constatés.</p>'
                        .'<h2>Comment nous intervenons</h2>'
                        .'<ul><li>Évaluation du profil et choix du programme</li>'
                        .'<li>Constitution et vérification du dossier</li>'
                        .'<li>Dépôt et suivi jusqu\'à la décision</li></ul>',
                    'price_note' => 'Sur devis, après évaluation du profil ['.self::MARQUE.']',
                    'included' => [
                        'Évaluation complète de votre profil',
                        'Choix du programme le plus adapté',
                        'Constitution et vérification du dossier',
                        'Dépôt de la demande',
                        "Suivi jusqu'à la décision",
                    ],
                    'excluded' => [
                        'Frais gouvernementaux et frais de visa',
                        'Traductions certifiées',
                        'Examens de langue (TCF, TEF, IELTS)',
                        'Évaluation des diplômes',
                        "Frais de voyage et d'installation",
                    ],
                    'highlight' => $mention,
                    'sort_order' => $ordre,
                    'is_published' => true,
                    'meta_description' => $resume,
                ],
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Questions fréquentes
    |--------------------------------------------------------------------------
    */

    private function faq(): void
    {
        $themes = [
            'Avant de déposer' => [
                ['Comment savoir si je suis admissible ?', 'Chaque programme a ses propres critères : âge, diplôme, expérience, niveau de langue. Le premier entretien sert à les vérifier point par point.'],
                ['Quels documents dois-je préparer ?', 'Au minimum votre CV au format canadien et votre résultat TCF ou TEF. Passeport, diplômes et relevés viennent ensuite.'],
                ['Faut-il parler anglais ou français ?', 'Les deux sont acceptés. Un test officiel est exigé dans presque tous les programmes.'],
                ['Combien de temps prend une demande ?', "De quelques semaines à plus d'un an selon le programme. Nous vous donnons une fourchette réaliste dès le départ."],
                ["Pouvez-vous garantir l'obtention du visa ?", 'Non, et personne ne le peut : la décision appartient aux autorités canadiennes. Méfiez-vous de tout cabinet qui l\'affirme.'],
            ],
            'Le dépôt de mon dossier' => [
                ['Dans quel format envoyer mes documents ?', "PDF, JPG ou PNG, jusqu'à 10 Mo par fichier. Une photo nette prise au téléphone convient parfaitement."],
                ['Que faire si ma connexion coupe pendant l\'envoi ?', "L'envoi reprend là où il s'est arrêté. Vos informations déjà saisies sont conservées sur votre téléphone."],
                ["Puis-je compléter mon dossier après l'avoir envoyé ?", 'Oui. Contactez-nous avec votre référence, nous ajouterons les pièces manquantes.'],
                ['Mes documents sont-ils en sécurité ?', 'Ils sont stockés sur un espace privé, sous un nom aléatoire, accessibles aux seuls membres habilités, et chaque consultation est enregistrée.'],
            ],
            'Après le dépôt' => [
                ["Comment suivre l'avancement de mon dossier ?", 'Avec votre référence et votre adresse e-mail, sur la page « Suivre mon dossier ».'],
                ['Que signifie chaque statut ?', 'Nouveau : reçu. En cours : à l\'étude. Incomplet : une pièce manque. Validé : dossier accepté par le cabinet. Rejeté : nous ne pouvons pas le défendre.'],
                ['Combien de temps mes données sont-elles conservées ?', "Un dossier sans activité pendant 36 mois est réexaminé, puis conservé ou effacé. Un dossier supprimé l'est définitivement au bout de 90 jours."],
                ['Comment demander la suppression de mon dossier ?', "Écrivez-nous avec votre référence : l'effacement est exécuté sans attendre les délais ci-dessus."],
            ],
            'Coûts et engagements' => [
                ['Combien coûte votre accompagnement ?', 'Nos honoraires dépendent du programme. Ils vous sont annoncés par écrit avant tout engagement ['.self::MARQUE.'].'],
                ['Les frais officiels sont-ils compris ?', 'Non. Les frais gouvernementaux, les traductions et les tests de langue restent à votre charge — ils sont listés sur chaque fiche de service.'],
                ['Que se passe-t-il si ma demande est refusée ?', 'Nous analysons le motif avec vous et examinons les recours ou les programmes alternatifs possibles.'],
            ],
        ];

        $ordre = 0;

        foreach ($themes as $theme => $questions) {
            $categorie = FaqCategory::query()->updateOrCreate(
                ['slug' => Str::slug($theme)],
                ['name' => $theme, 'sort_order' => $ordre++, 'is_published' => true],
            );

            foreach ($questions as $rang => [$question, $reponse]) {
                Faq::query()->updateOrCreate(
                    ['faq_category_id' => $categorie->getKey(), 'question' => $question],
                    ['answer' => '<p>'.$reponse.'</p>', 'sort_order' => $rang, 'is_published' => true],
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Équipe
    |--------------------------------------------------------------------------
    */

    private function equipe(): void
    {
        $membres = [
            ['Lucie N.', 'Fondatrice et conseillère principale', "Accompagne des dossiers d'immigration vers le Canada depuis huit ans."],
            ['Aïcha N.', 'Conseillère — programmes fédéraux', 'Suit les dossiers Entrée Express et les demandes de permis de travail.'],
            ['Serge M.', 'Conseiller — Québec et études', "Prend en charge le PSTQ et les demandes de permis d'études."],
            ['Fatou D.', 'Assistante administrative', 'Vérifie la conformité des pièces avant chaque dépôt.'],
        ];

        foreach ($membres as $ordre => [$nom, $role, $bio]) {
            TeamMember::query()->updateOrCreate(
                ['name' => $nom],
                [
                    'role' => $role,
                    'bio' => $bio.' ['.self::MARQUE.']',
                    'sort_order' => $ordre,
                    'is_published' => true,
                ],
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Témoignages
    |--------------------------------------------------------------------------
    */

    private function temoignages(): void
    {
        $temoignages = [
            ['Aminata', 'Cameroun', 'Entrée Express, 2025', 'Le dossier a été monté en deux mois. On m\'a expliqué chaque pièce, et ce qui pouvait bloquer.'],
            ['Jean-Baptiste', 'Cameroun', "Permis d'études, 2025", "J'ai tout envoyé depuis mon téléphone, en plusieurs fois. La connexion a coupé deux fois, ça a repris tout seul."],
            ['Grace', "Côte d'Ivoire", 'Regroupement familial, 2024', "On m'a dit dès le début que mon premier projet n'était pas défendable. J'ai apprécié la franchise."],
            ['Ousmane', 'Cameroun', 'Permis de travail, 2025', "Le suivi en ligne évite d'appeler pour savoir où en est le dossier."],
            ['Marie-Claire', 'Cameroun', 'PSTQ — Québec, 2024', "Les frais annoncés au départ sont ceux que j'ai payés. Rien de caché."],
        ];

        foreach ($temoignages as $ordre => [$prenom, $pays, $programme, $contenu]) {
            Testimonial::query()->updateOrCreate(
                ['author_name' => $prenom.' ['.self::MARQUE.']'],
                [
                    'author_country' => $pays,
                    'author_programme' => $programme,
                    'content' => $contenu,
                    'sort_order' => $ordre,
                    'is_published' => true,
                ],
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Blocs de page
    |--------------------------------------------------------------------------
    */

    private function blocs(): void
    {
        $marque = self::MARQUE;

        $blocs = [
            ['accueil', SectionType::Cartes, [
                'titre' => 'Pourquoi passer par un cabinet',
                'introduction' => 'Trois raisons qui reviennent dans les dossiers refusés.',
                'cartes' => [
                    ['titre' => 'Une pièce manquante', 'texte' => 'La plupart des refus tiennent à un document absent ou non conforme.'],
                    ['titre' => 'Un programme mal choisi', 'texte' => 'Postuler au mauvais programme fait perdre des mois et des frais.'],
                    ['titre' => 'Un délai dépassé', 'texte' => 'Les invitations ont une durée de validité courte, souvent sous-estimée.'],
                ],
            ]],

            ['accueil', SectionType::Citation, [
                'texte' => "On ne vend pas un visa. On prépare un dossier défendable, et on le dit quand il ne l'est pas.",
                'auteur' => 'Lucie N.',
                'fonction' => 'Fondatrice ['.$marque.']',
            ]],

            ['services', SectionType::Etapes, [
                'titre' => 'Comment se déroule un accompagnement',
                'introduction' => 'Le même déroulé quel que soit le programme.',
                'etapes' => [
                    ['titre' => "Entretien d'évaluation", 'description' => 'Une heure pour faire le tour de votre parcours.'],
                    ['titre' => 'Choix du programme', 'description' => 'Nous vous présentons les options réellement ouvertes.'],
                    ['titre' => 'Constitution du dossier', 'description' => 'Liste de pièces, vérification, mise en forme.'],
                    ['titre' => 'Dépôt et suivi', 'description' => "Nous déposons, puis suivons jusqu'à la décision."],
                ],
            ]],

            ['a-propos', SectionType::TexteImage, [
                'titre' => 'Notre approche',
                'texte' => '<p>Nous refusons les dossiers que nous ne pensons pas pouvoir défendre. C\'est moins de '
                    ."chiffre d'affaires à court terme, et beaucoup moins de candidats déçus. [{$marque}]</p>",
                'position_image' => 'droite',
            ]],

            ['a-propos', SectionType::Chiffres, [
                'titre' => 'Le cabinet en quelques chiffres',
                'chiffres' => [
                    ['valeur' => '8 ['.$marque.']', 'libelle' => "Années d'activité"],
                    ['valeur' => '450 ['.$marque.']', 'libelle' => 'Dossiers accompagnés'],
                    ['valeur' => '6 ['.$marque.']', 'libelle' => 'Pays de résidence couverts'],
                ],
            ]],

            ['contact', SectionType::Cta, [
                'titre' => 'Une question avant de déposer ?',
                'texte' => 'Écrivez-nous, nous répondons sous 72 heures ouvrées.',
                'bouton_libelle' => 'Déposer mon dossier',
                'bouton_url' => '/deposer-mon-dossier',
            ]],
        ];

        foreach ($blocs as $ordre => [$page, $type, $data]) {
            $existant = PageSection::query()
                ->where('page', $page)
                ->where('type', $type->value)
                ->first();

            if ($existant !== null) {
                $existant->update(['data' => $data, 'is_published' => true]);

                continue;
            }

            PageSection::query()->create([
                'page' => $page,
                'type' => $type->value,
                'sort_order' => $ordre,
                'is_published' => true,
                'data' => $data,
            ]);
        }

        // Les blocs livrés par ContentSeeder portent des placeholders : on les
        // publie aussi, pour que la démonstration montre toutes les pages.
        PageSection::query()->update(['is_published' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | Dossiers de candidats
    |--------------------------------------------------------------------------
    */

    private function dossiers(): void
    {
        if (Application::query()->where('email', 'like', '%@demo.test')->exists()) {
            $this->command->line('  Dossiers de démonstration déjà présents, ignorés.');

            return;
        }

        /** @var User $admin */
        $admin = User::query()->where('email', 'admin@demo.test')->sole();

        $disque = Storage::disk('local');

        // Un dossier par statut, pour voir chaque badge et chaque filtre.
        foreach (ApplicationStatus::cases() as $rang => $statut) {
            $application = Application::factory()
                ->status($statut)
                ->create([
                    'email' => 'candidat'.($rang + 1).'@demo.test',
                    'internal_notes' => $statut === ApplicationStatus::Incomplet
                        ? 'Relancer pour le relevé de notes manquant. ['.self::MARQUE.']'
                        : null,
                ]);

            $this->pieces($application, $disque);

            if (in_array($statut, [ApplicationStatus::EnCours, ApplicationStatus::Valide], true)) {
                ApplicationUpdate::factory()->create([
                    'application_id' => $application->getKey(),
                    'user_id' => $admin->getKey(),
                    'status' => $statut,
                    'public_message' => 'Votre dossier a été examiné, nous revenons vers vous très vite. ['.self::MARQUE.']',
                ]);
            }
        }

        // De quoi faire travailler les listes, les filtres et la pagination.
        Application::factory()
            ->count(15)
            ->sequence(fn ($sequence) => [
                'status' => ApplicationStatus::cases()[$sequence->index % count(ApplicationStatus::cases())],
                'email' => 'candidat'.($sequence->index + 10).'@demo.test',
                'country_of_residence' => ['Cameroun', "Côte d'Ivoire", 'Sénégal', 'Gabon', 'Tchad'][$sequence->index % 5],
            ])
            ->create()
            ->each(fn (Application $application) => $this->pieces($application, $disque));

        // La file de conservation : deux dossiers échus, un à J-30, un à J-7.
        //
        // L'adresse en @demo.test n'est pas décorative : c'est elle qui permet
        // à « ln:demo --purge » de retrouver ces dossiers. Sans elle, ils
        // resteraient en base avec leurs pièces sur le disque.
        Application::factory()->count(2)->echu()
            ->sequence(fn ($sequence) => ['email' => 'echu'.($sequence->index + 1).'@demo.test'])
            ->create(['status' => ApplicationStatus::Valide])
            ->each(fn (Application $application) => $this->pieces($application, $disque));

        Application::factory()->echeanceDans(30)->create([
            'status' => ApplicationStatus::EnCours,
            'email' => 'echeance-30@demo.test',
        ]);

        Application::factory()->echeanceDans(7)->create([
            'status' => ApplicationStatus::Nouveau,
            'email' => 'echeance-7@demo.test',
        ]);

        // Supprimé depuis plus de 90 jours : celui-là, la commande planifiée a
        // le droit de l'effacer.
        Application::factory()->trashed()->create([
            'status' => ApplicationStatus::Rejete,
            'email' => 'supprime@demo.test',
        ]);

        // On joue la bascule tout de suite : sans elle, la file d'attente et le
        // bandeau du tableau de bord n'apparaîtraient qu'après le premier
        // passage nocturne de ln:purge-applications.
        $signales = app(PurgeExpiredApplications::class)->signalerLesEchus();

        $this->command->line('  '.Application::withTrashed()->count().' dossiers créés, pièces comprises.');
        $this->command->line('  '.$signales.' dossier(s) en attente de décision, pour voir le bandeau.');
    }

    /**
     * Attache des pièces au dossier, et les écrit réellement sur le disque privé.
     *
     * Un chemin en base sans fichier derrière donnerait un back-office qui a
     * l'air de marcher jusqu'au premier téléchargement.
     */
    private function pieces(Application $application, Filesystem $disque): void
    {
        foreach ([DocumentType::Cv, DocumentType::TcfTef, DocumentType::Passeport, DocumentType::Diplome] as $type) {
            $document = Document::factory()->type($type)->create([
                'application_id' => $application->getKey(),
                'path' => 'documents/'.$application->reference.'/'.Str::uuid()->toString().'.pdf',
            ]);

            // Un PDF minimal mais valide : il s'ouvre réellement.
            $disque->put(
                $document->path,
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
            );
        }
    }
}
