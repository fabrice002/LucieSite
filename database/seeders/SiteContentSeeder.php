<?php

namespace Database\Seeders;

use App\Models\SiteContent;
use Illuminate\Database\Seeder;

/**
 * Source de vérité initiale des textes du site public.
 *
 * Le seeder utilise updateOrCreate sur le couple (key, locale) : le relancer
 * ne doit jamais écraser une modification faite depuis le back-office. Les
 * blocs déjà présents sont donc laissés intacts.
 *
 * Les textes rédactionnels sont volontairement des placeholders. Seuls les
 * libellés fonctionnels (boutons, champs, navigation) sont définitifs.
 */
class SiteContentSeeder extends Seeder
{
    private const TODO = '[À COMPLÉTER PAR LA CLIENTE]';

    /**
     * Seed the editable contents of the public site.
     */
    public function run(): void
    {
        foreach ($this->blocks() as $key => $block) {
            $existing = SiteContent::query()
                ->where('key', $key)
                ->where('locale', 'fr')
                ->first();

            if ($existing === null) {
                SiteContent::query()->create([
                    'key' => $key,
                    'locale' => 'fr',
                    'label' => $block['label'],
                    'content' => $block['content'],
                ]);

                continue;
            }

            // Le bloc existe déjà : on n'ajoute que les clés absentes, pour ne
            // jamais écraser un texte modifié depuis le back-office.
            $missing = array_diff_key($block['content'], $existing->content);

            if ($missing !== []) {
                $existing->update(['content' => $existing->content + $missing]);
            }
        }
    }

    /**
     * Get every block of the public site.
     *
     * @return array<string, array{label: string, content: array<string, string>}>
     */
    private function blocks(): array
    {
        return [
            'global' => [
                'label' => 'Général (nom du site, pied de page)',
                'content' => [
                    'nom_site' => 'LN Immigration',
                    'baseline' => self::TODO,
                    'nav_accueil' => 'Accueil',
                    'nav_services' => 'Services',
                    'nav_a_propos' => 'À propos',
                    'nav_temoignages' => 'Témoignages',
                    'nav_faq' => 'FAQ',
                    'nav_contact' => 'Contact',
                    'nav_deposer' => 'Déposer mon dossier',
                    'nav_suivre' => 'Suivre mon dossier',
                    'footer_adresse' => self::TODO,
                    'footer_telephone' => self::TODO,
                    'footer_email' => self::TODO,
                    'footer_mentions' => 'Mentions légales',
                    'footer_confidentialite' => 'Politique de confidentialité',
                    'footer_copyright' => 'Tous droits réservés.',
                ],
            ],

            'accueil' => [
                'label' => "Page d'accueil",
                'content' => [
                    'meta_titre' => 'LN Immigration — Accompagnement à l\'immigration au Canada',
                    'meta_description' => self::TODO,
                    'hero_titre' => self::TODO,
                    'hero_sous_titre' => self::TODO,
                    'hero_bouton' => 'Déposer mon dossier',
                    'hero_bouton_secondaire' => 'Suivre mon dossier',
                    'section_services_titre' => 'Nos services',
                    'section_services_intro' => self::TODO,
                    'section_etapes_titre' => 'Comment ça se passe',
                    'etape_1_titre' => 'Vous déposez votre dossier',
                    'etape_1_texte' => self::TODO,
                    'etape_2_titre' => 'Nous l\'étudions',
                    'etape_2_texte' => self::TODO,
                    'etape_3_titre' => 'Nous vous accompagnons',
                    'etape_3_texte' => self::TODO,
                    'cta_titre' => self::TODO,
                    'cta_bouton' => 'Commencer maintenant',
                ],
            ],

            'depot' => [
                'label' => 'Page « Déposer mon dossier »',
                'content' => [
                    'meta_titre' => 'Déposer mon dossier — LN Immigration',
                    'meta_description' => self::TODO,
                    'titre' => 'Déposer mon dossier',
                    'introduction' => self::TODO,
                    'section_identite' => 'Vos informations',
                    'label_prenom' => 'Prénom',
                    'label_nom' => 'Nom',
                    'label_email' => 'Adresse e-mail',
                    'label_telephone' => 'Téléphone',
                    'aide_telephone' => 'Au format international, par exemple +237 6 XX XX XX XX.',
                    'label_pays' => 'Pays de résidence',
                    'label_programme' => 'Programme visé',
                    'aide_programme' => 'Si vous ne savez pas encore, laissez ce champ vide.',
                    'label_message' => 'Votre message',
                    'aide_message' => 'Facultatif. Précisez ici tout ce qui nous aiderait à comprendre votre situation.',
                    'section_documents' => 'Vos documents',
                    'aide_documents' => 'Formats acceptés : PDF, JPG ou PNG. 10 Mo maximum par fichier.',
                    'label_cv' => 'CV au format canadien',
                    'aide_cv' => 'Obligatoire.',
                    'label_tcf_tef' => 'Résultat TCF ou TEF',
                    'aide_tcf_tef' => 'Obligatoire.',
                    'label_passeport' => 'Passeport',
                    'aide_passeport' => 'Facultatif.',
                    'label_diplomes' => 'Diplômes',
                    'aide_diplomes' => 'Facultatif. Vous pouvez en envoyer plusieurs.',
                    'label_autres' => 'Autres documents',
                    'aide_autres' => 'Facultatif. Vous pouvez en envoyer plusieurs.',
                    'bouton_envoyer' => 'Envoyer mon dossier',
                    'mention_donnees' => self::TODO,
                ],
            ],

            'confirmation' => [
                'label' => 'Page de confirmation de dépôt',
                'content' => [
                    'meta_titre' => 'Dossier reçu — LN Immigration',
                    'titre' => 'Votre dossier a bien été reçu',
                    'intro' => 'Conservez précieusement votre référence de suivi : elle vous sera demandée pour connaître l\'avancement de votre dossier.',
                    'label_reference' => 'Votre référence de suivi',
                    'suite' => self::TODO,
                    'bouton_suivi' => 'Suivre mon dossier',
                    'bouton_accueil' => 'Retour à l\'accueil',
                ],
            ],

            'suivi' => [
                'label' => 'Page « Suivre mon dossier »',
                'content' => [
                    'meta_titre' => 'Suivre mon dossier — LN Immigration',
                    'meta_description' => self::TODO,
                    'titre' => 'Suivre mon dossier',
                    'introduction' => 'Saisissez la référence reçue lors du dépôt ainsi que l\'adresse e-mail utilisée.',
                    'label_reference' => 'Référence du dossier',
                    'aide_reference' => 'Par exemple : LN-2026-00147',
                    'label_email' => 'Adresse e-mail',
                    'bouton' => 'Consulter mon dossier',
                    'resultat_titre' => 'État de votre dossier',
                    'resultat_statut' => 'Statut',
                    'resultat_maj' => 'Dernière mise à jour',
                    'introuvable' => 'Aucun dossier ne correspond à cette référence et à cette adresse e-mail.',
                    'aide_contact' => self::TODO,
                ],
            ],
        ];
    }
}
