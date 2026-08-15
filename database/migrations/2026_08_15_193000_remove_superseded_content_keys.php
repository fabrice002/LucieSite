<?php

use App\Models\SiteContent;
use App\Support\SiteContentRepository;
use Illuminate\Database\Migrations\Migration;

/**
 * Retire les clés de textes devenues sans objet.
 *
 * Les services et les questions fréquentes étaient au départ une poignée de
 * champs figés dans « Textes du site ». Ils ont depuis leur propre table, avec
 * un nombre d'entrées libre. Les anciennes clés ne sont plus lues par aucune
 * vue, mais restaient affichées dans le back-office : la cliente aurait perdu
 * du temps à remplir des champs sans effet.
 *
 * Les blocs « services » et « faq » eux-mêmes restent : ils portent toujours le
 * titre de la page, ses méta-données, les libellés du champ de recherche et le
 * bandeau d'appel à l'action.
 *
 * Une clé que la cliente aurait déjà remplie n'est pas touchée : mieux vaut un
 * champ inutile qu'un texte détruit sans prévenir.
 */
return new class extends Migration
{
    /**
     * @return array<string, list<string>>
     */
    private function clesRetirees(): array
    {
        $services = ['tarifs_titre', 'tarifs_texte'];
        $faq = [];

        foreach (range(1, 6) as $rang) {
            $services[] = "service_{$rang}_titre";
            $services[] = "service_{$rang}_texte";
            $faq[] = "question_{$rang}";
            $faq[] = "reponse_{$rang}";
        }

        return ['services' => $services, 'faq' => $faq];
    }

    public function up(): void
    {
        foreach ($this->clesRetirees() as $bloc => $cles) {
            $contenu = SiteContent::query()->where('key', $bloc)->first();

            if ($contenu === null) {
                continue;
            }

            $valeurs = $contenu->content;

            foreach ($cles as $cle) {
                $valeur = $valeurs[$cle] ?? null;

                if (! array_key_exists($cle, $valeurs)) {
                    continue;
                }

                // Rédigée par la cliente : on la laisse, quitte à ce qu'elle
                // reste visible sans servir.
                $rempli = is_string($valeur)
                    && trim($valeur) !== ''
                    && ! str_contains($valeur, SiteContentRepository::PLACEHOLDER);

                if ($rempli) {
                    continue;
                }

                unset($valeurs[$cle]);
            }

            $contenu->update(['content' => $valeurs]);
        }
    }

    public function down(): void
    {
        foreach ($this->clesRetirees() as $bloc => $cles) {
            $contenu = SiteContent::query()->where('key', $bloc)->first();

            if ($contenu === null) {
                continue;
            }

            $valeurs = $contenu->content;

            foreach ($cles as $cle) {
                $valeurs[$cle] ??= SiteContentRepository::PLACEHOLDER;
            }

            $contenu->update(['content' => $valeurs]);
        }
    }
};
