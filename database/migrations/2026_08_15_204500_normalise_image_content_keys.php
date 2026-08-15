<?php

use App\Models\SiteContent;
use App\Support\SiteContentRepository;
use Illuminate\Database\Migrations\Migration;

/**
 * Vide les clés d'image qui portaient encore un placeholder.
 *
 * Ces clés sont désormais rendues en champ de téléversement dans le
 * back-office. Une valeur « [À COMPLÉTER PAR LA CLIENTE] » y serait interprétée
 * comme un nom de fichier : le champ afficherait une image cassée, et la
 * cliente ne pourrait pas s'en débarrasser.
 *
 * Côté public rien ne change : content_filled() écartait déjà cette valeur.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (SiteContent::all() as $contenu) {
            $valeurs = $contenu->content;
            $modifie = false;

            foreach ($valeurs as $cle => $valeur) {
                $estImage = str_ends_with($cle, '_image') || $cle === 'image';

                if (! $estImage || ! str_contains($valeur, SiteContentRepository::PLACEHOLDER)) {
                    continue;
                }

                $valeurs[$cle] = '';
                $modifie = true;
            }

            if ($modifie) {
                $contenu->update(['content' => $valeurs]);
            }
        }
    }

    /**
     * Rien à défaire : remettre un placeholder dans un champ de fichier
     * recréerait exactement le défaut que cette migration corrige.
     */
    public function down(): void {}
};
