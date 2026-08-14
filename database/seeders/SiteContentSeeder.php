<?php

namespace Database\Seeders;

use App\Models\SiteContent;
use Illuminate\Database\Seeder;

/**
 * Source de vérité initiale des textes du site public.
 *
 * Chaque bloc devient une fiche éditable dans le back-office : ajouter une clé
 * ici, c'est ajouter un champ dans l'interface d'administration. Relancer le
 * seeder n'écrase jamais un texte modifié par la cliente, il complète seulement
 * les clés absentes.
 *
 * Les textes rédactionnels sont des placeholders. Seuls les libellés
 * fonctionnels (boutons, champs, navigation) sont définitifs.
 */
class SiteContentSeeder extends Seeder
{
    private const TODO = '[À COMPLÉTER PAR LA CLIENTE]';

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
     * @return array<string, array{label: string, content: array<string, string>}>
     */
    private function blocks(): array
    {
        return [
            'global' => [
                'label' => 'Général — nom du site, navigation, pied de page',
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
                    'footer_titre_navigation' => 'Le cabinet',
                    'footer_titre_contact' => 'Nous joindre',
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
                    'meta_titre' => "LN Immigration — Accompagnement à l'immigration au Canada",
                    'meta_description' => self::TODO,
                    'hero_titre' => self::TODO,
                    'hero_sous_titre' => self::TODO,
                    'hero_bouton' => 'Déposer mon dossier',
                    'hero_bouton_secondaire' => 'Suivre mon dossier',
                    'section_services_titre' => 'Nos services',
                    'section_services_intro' => self::TODO,
                    'section_services_bouton' => 'Voir tous nos services',
                    'section_etapes_titre' => 'Comment ça se passe',
                    'etape_1_titre' => 'Vous déposez votre dossier',
                    'etape_1_texte' => self::TODO,
                    'etape_2_titre' => "Nous l'étudions",
                    'etape_2_texte' => self::TODO,
                    'etape_3_titre' => 'Nous vous accompagnons',
                    'etape_3_texte' => self::TODO,
                    'section_temoignages_titre' => 'Ils nous ont fait confiance',
                    'section_temoignages_bouton' => 'Lire tous les témoignages',
                    'cta_titre' => self::TODO,
                    'cta_texte' => self::TODO,
                    'cta_bouton' => 'Commencer maintenant',
                ],
            ],

            'services' => [
                'label' => 'Page « Services »',
                'content' => [
                    'meta_titre' => 'Nos services — LN Immigration',
                    'meta_description' => self::TODO,
                    'titre' => 'Nos services',
                    'introduction' => self::TODO,
                    'service_1_titre' => self::TODO,
                    'service_1_texte' => self::TODO,
                    'service_2_titre' => self::TODO,
                    'service_2_texte' => self::TODO,
                    'service_3_titre' => self::TODO,
                    'service_3_texte' => self::TODO,
                    'service_4_titre' => self::TODO,
                    'service_4_texte' => self::TODO,
                    'service_5_titre' => self::TODO,
                    'service_5_texte' => self::TODO,
                    'service_6_titre' => self::TODO,
                    'service_6_texte' => self::TODO,
                    'tarifs_titre' => 'Nos tarifs',
                    'tarifs_texte' => self::TODO,
                    'cta_titre' => self::TODO,
                    'cta_bouton' => 'Déposer mon dossier',
                ],
            ],

            'a_propos' => [
                'label' => 'Page « À propos »',
                'content' => [
                    'meta_titre' => 'À propos — LN Immigration',
                    'meta_description' => self::TODO,
                    'titre' => 'À propos du cabinet',
                    'introduction' => self::TODO,
                    'histoire_titre' => 'Notre histoire',
                    'histoire_texte' => self::TODO,
                    'mission_titre' => 'Notre mission',
                    'mission_texte' => self::TODO,
                    'valeur_1_titre' => self::TODO,
                    'valeur_1_texte' => self::TODO,
                    'valeur_2_titre' => self::TODO,
                    'valeur_2_texte' => self::TODO,
                    'valeur_3_titre' => self::TODO,
                    'valeur_3_texte' => self::TODO,
                    'equipe_titre' => "L'équipe",
                    'equipe_texte' => self::TODO,
                    'cta_titre' => self::TODO,
                    'cta_bouton' => 'Déposer mon dossier',
                ],
            ],

            'temoignages' => [
                'label' => 'Page « Témoignages »',
                'content' => [
                    'meta_titre' => 'Témoignages — LN Immigration',
                    'meta_description' => self::TODO,
                    'titre' => 'Ils nous ont fait confiance',
                    'introduction' => self::TODO,
                    'aucun' => 'Les premiers témoignages seront publiés prochainement.',
                    'cta_titre' => self::TODO,
                    'cta_bouton' => 'Déposer mon dossier',
                ],
            ],

            'faq' => [
                'label' => 'Page « FAQ »',
                'content' => [
                    'meta_titre' => 'Questions fréquentes — LN Immigration',
                    'meta_description' => self::TODO,
                    'titre' => 'Questions fréquentes',
                    'introduction' => self::TODO,
                    'question_1' => self::TODO,
                    'reponse_1' => self::TODO,
                    'question_2' => self::TODO,
                    'reponse_2' => self::TODO,
                    'question_3' => self::TODO,
                    'reponse_3' => self::TODO,
                    'question_4' => self::TODO,
                    'reponse_4' => self::TODO,
                    'question_5' => self::TODO,
                    'reponse_5' => self::TODO,
                    'question_6' => self::TODO,
                    'reponse_6' => self::TODO,
                    'cta_titre' => 'Vous ne trouvez pas votre réponse ?',
                    'cta_bouton' => 'Nous contacter',
                ],
            ],

            'contact' => [
                'label' => 'Page « Contact »',
                'content' => [
                    'meta_titre' => 'Nous contacter — LN Immigration',
                    'meta_description' => self::TODO,
                    'titre' => 'Nous contacter',
                    'introduction' => self::TODO,
                    'adresse_titre' => 'Adresse',
                    'adresse_texte' => self::TODO,
                    'telephone_titre' => 'Téléphone',
                    'telephone_texte' => self::TODO,
                    'email_titre' => 'Adresse e-mail',
                    'email_texte' => self::TODO,
                    'horaires_titre' => "Horaires d'ouverture",
                    'horaires_texte' => self::TODO,
                    'depot_titre' => 'Vous souhaitez nous confier votre dossier ?',
                    'depot_texte' => self::TODO,
                    'depot_bouton' => 'Déposer mon dossier',
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
                    'bouton_accueil' => "Retour à l'accueil",
                ],
            ],

            'suivi' => [
                'label' => 'Page « Suivre mon dossier »',
                'content' => [
                    'meta_titre' => 'Suivre mon dossier — LN Immigration',
                    'meta_description' => self::TODO,
                    'titre' => 'Suivre mon dossier',
                    'introduction' => "Saisissez la référence reçue lors du dépôt ainsi que l'adresse e-mail utilisée.",
                    'label_reference' => 'Référence du dossier',
                    'aide_reference' => 'Par exemple : LN-2026-00147',
                    'label_email' => 'Adresse e-mail',
                    'bouton' => 'Consulter mon dossier',
                    'resultat_titre' => 'État de votre dossier',
                    'resultat_statut' => 'Statut',
                    'resultat_maj' => 'Dernière mise à jour',
                    'introuvable' => 'Aucun dossier ne correspond à cette référence et à cette adresse e-mail.',
                    'aide_contact' => self::TODO,

                    'messages_titre' => 'Messages du cabinet',
                    'messages_vide' => 'Aucun message pour le moment. Vous serez prévenu par e-mail dès que votre dossier évoluera.',

                    // Modèles proposés dans le back-office au moment d'informer
                    // le candidat. L'administratrice peut les modifier avant envoi.
                    'modele_nouveau' => 'Nous avons bien reçu votre dossier et nous vous en remercions. Il sera étudié dans les meilleurs délais.',
                    'modele_en_cours' => 'Votre dossier est en cours d\'étude par notre équipe. Nous revenons vers vous dès que cette étape est terminée.',
                    'modele_incomplet' => 'Après examen, il manque une ou plusieurs pièces à votre dossier. Merci de nous les faire parvenir afin que nous puissions poursuivre son traitement.',
                    'modele_valide' => 'Votre dossier est complet et a été validé. Nous vous recontactons prochainement pour la suite de votre démarche.',
                    'modele_rejete' => 'Après examen attentif, nous ne sommes pas en mesure de donner suite à votre dossier en l\'état.',
                ],
            ],

            'email_suivi' => [
                'label' => 'E-mail « Votre dossier a été mis à jour »',
                'content' => [
                    'objet' => 'Votre dossier :reference a été mis à jour',
                    'salutation' => 'Bonjour :prenom,',
                    'intro' => 'L\'état de votre dossier vient d\'évoluer.',
                    'ligne_reference' => 'Référence :',
                    'ligne_statut' => 'Nouvel état :',
                    'invitation' => 'Connectez-vous à la page de suivi avec votre référence et votre adresse e-mail pour consulter le détail.',
                    'bouton' => 'Consulter mon dossier',
                    'rappel_securite' => 'Par sécurité, aucun détail de votre dossier n\'est transmis par e-mail.',
                    'signature' => 'L\'équipe LN Immigration',
                ],
            ],

            'mentions_legales' => [
                'label' => 'Page « Mentions légales »',
                'content' => [
                    'meta_titre' => 'Mentions légales — LN Immigration',
                    'titre' => 'Mentions légales',
                    'editeur_titre' => 'Éditeur du site',
                    'editeur_html' => self::TODO,
                    'hebergeur_titre' => 'Hébergeur',
                    'hebergeur_html' => self::TODO,
                    'propriete_titre' => 'Propriété intellectuelle',
                    'propriete_html' => self::TODO,
                    'responsabilite_titre' => 'Limitation de responsabilité',
                    'responsabilite_html' => self::TODO,
                ],
            ],

            'confidentialite' => [
                'label' => 'Page « Politique de confidentialité »',
                'content' => [
                    'meta_titre' => 'Politique de confidentialité — LN Immigration',
                    'titre' => 'Politique de confidentialité',
                    'introduction_html' => self::TODO,
                    'donnees_titre' => 'Données collectées',
                    'donnees_html' => self::TODO,
                    'finalite_titre' => 'Pourquoi nous les collectons',
                    'finalite_html' => self::TODO,
                    'conservation_titre' => 'Durée de conservation',
                    'conservation_html' => self::TODO,
                    'securite_titre' => 'Sécurité de vos documents',
                    'securite_html' => self::TODO,
                    'droits_titre' => 'Vos droits',
                    'droits_html' => self::TODO,
                    'contact_titre' => 'Nous écrire',
                    'contact_html' => self::TODO,
                ],
            ],
        ];
    }
}
