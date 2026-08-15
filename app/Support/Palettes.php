<?php

namespace App\Support;

/**
 * Les palettes proposées en un clic dans la section « Apparence ».
 *
 * Choisir quatre couleurs qui s'accordent est un métier. La plupart du temps la
 * cliente prendra une palette et n'ira jamais plus loin — les sélecteurs de
 * couleur libres restent disponibles en dessous, pour qui veut.
 *
 * Chaque palette respecte le contraste minimum WCAG AA entre la couleur
 * principale et le texte qui se pose dessus. Un test le vérifie.
 */
class Palettes
{
    /**
     * @return array<string, array{nom: string, description: string, couleurs: array<string, string>}>
     */
    public static function toutes(): array
    {
        return [
            'bleu_institutionnel' => [
                'nom' => 'Bleu institutionnel',
                'description' => 'Sobre et rassurant. Le choix par défaut du secteur.',
                'couleurs' => [
                    'couleur_principale' => '#1d4ed8',
                    'couleur_secondaire' => '#0f766e',
                    'couleur_accent' => '#b45309',
                    'couleur_texte_sur_principale' => '#ffffff',
                ],
            ],

            'marine_et_or' => [
                'nom' => 'Marine et or',
                'description' => 'Plus formel, proche des codes administratifs.',
                'couleurs' => [
                    'couleur_principale' => '#1e3a8a',
                    'couleur_secondaire' => '#a16207',
                    'couleur_accent' => '#ca8a04',
                    'couleur_texte_sur_principale' => '#ffffff',
                ],
            ],

            'vert_foret' => [
                'nom' => 'Vert forêt',
                'description' => 'Plus chaleureux, moins courant dans le secteur.',
                'couleurs' => [
                    'couleur_principale' => '#166534',
                    'couleur_secondaire' => '#4d7c0f',
                    'couleur_accent' => '#b45309',
                    'couleur_texte_sur_principale' => '#ffffff',
                ],
            ],

            'bordeaux_et_creme' => [
                'nom' => 'Bordeaux et crème',
                'description' => 'Un ton plus posé, plus classique.',
                'couleurs' => [
                    'couleur_principale' => '#881337',
                    'couleur_secondaire' => '#9f1239',
                    'couleur_accent' => '#a16207',
                    'couleur_texte_sur_principale' => '#ffffff',
                ],
            ],

            'ardoise_et_cuivre' => [
                'nom' => 'Ardoise et cuivre',
                'description' => 'Neutre et contemporain, sans couleur dominante.',
                'couleurs' => [
                    'couleur_principale' => '#334155',
                    'couleur_secondaire' => '#475569',
                    'couleur_accent' => '#c2410c',
                    'couleur_texte_sur_principale' => '#ffffff',
                ],
            ],
        ];
    }

    /**
     * Les couleurs d'une palette, ou null si elle n'existe pas.
     *
     * @return array<string, string>|null
     */
    public static function couleurs(string $identifiant): ?array
    {
        return self::toutes()[$identifiant]['couleurs'] ?? null;
    }

    /**
     * Libellés pour un champ de sélection.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(fn (array $palette): string => $palette['nom'], self::toutes());
    }

    /**
     * Descriptions, pour un champ à boutons radio.
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return array_map(fn (array $palette): string => $palette['description'], self::toutes());
    }
}
