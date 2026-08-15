<?php

namespace App\Support;

/**
 * Calculs de couleur : contraste WCAG et nuances dérivées.
 *
 * Écrit à la main plutôt qu'ajouté en dépendance : il s'agit d'une soixantaine
 * de lignes d'arithmétique dont la formule est figée par la norme.
 *
 * @see https://www.w3.org/TR/WCAG21/#dfn-contrast-ratio
 */
class Couleur
{
    /**
     * Le minimum exigé par WCAG AA pour du texte de taille normale.
     */
    public const CONTRASTE_MINIMUM = 4.5;

    /**
     * Une écriture hexadécimale valide, à trois ou six chiffres.
     */
    public static function estValide(?string $couleur): bool
    {
        return is_string($couleur) && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $couleur) === 1;
    }

    /**
     * Normalise en #rrggbb minuscule.
     */
    public static function normaliser(string $couleur): string
    {
        $chiffres = ltrim(trim($couleur), '#');

        if (strlen($chiffres) === 3) {
            $chiffres = $chiffres[0].$chiffres[0].$chiffres[1].$chiffres[1].$chiffres[2].$chiffres[2];
        }

        return '#'.strtolower($chiffres);
    }

    /**
     * Les trois canaux, de 0 à 255.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function versRgb(string $couleur): array
    {
        $chiffres = ltrim(self::normaliser($couleur), '#');

        return [
            (int) hexdec(substr($chiffres, 0, 2)),
            (int) hexdec(substr($chiffres, 2, 2)),
            (int) hexdec(substr($chiffres, 4, 2)),
        ];
    }

    /**
     * Depuis trois canaux.
     *
     * @param  array{0: int|float, 1: int|float, 2: int|float}  $rgb
     */
    public static function depuisRgb(array $rgb): string
    {
        return '#'.implode('', array_map(
            fn (int|float $canal): string => str_pad(
                dechex((int) round(max(0, min(255, $canal)))),
                2,
                '0',
                STR_PAD_LEFT,
            ),
            $rgb,
        ));
    }

    /**
     * Luminance relative, telle que définie par WCAG.
     */
    public static function luminance(string $couleur): float
    {
        $canaux = array_map(function (int $canal): float {
            $proportion = $canal / 255;

            return $proportion <= 0.03928
                ? $proportion / 12.92
                : (($proportion + 0.055) / 1.055) ** 2.4;
        }, self::versRgb($couleur));

        return 0.2126 * $canaux[0] + 0.7152 * $canaux[1] + 0.0722 * $canaux[2];
    }

    /**
     * Rapport de contraste entre deux couleurs, de 1 (identiques) à 21.
     */
    public static function contraste(string $premiere, string $seconde): float
    {
        $a = self::luminance($premiere);
        $b = self::luminance($seconde);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Le contraste satisfait-il le minimum WCAG AA ?
     */
    public static function contrasteSuffisant(string $premiere, string $seconde): bool
    {
        return self::contraste($premiere, $seconde) >= self::CONTRASTE_MINIMUM;
    }

    /**
     * Mélange deux couleurs. $part vaut la proportion de $vers, de 0 à 1.
     */
    public static function melanger(string $couleur, string $vers, float $part): string
    {
        $depart = self::versRgb($couleur);
        $arrivee = self::versRgb($vers);

        return self::depuisRgb([
            $depart[0] + ($arrivee[0] - $depart[0]) * $part,
            $depart[1] + ($arrivee[1] - $depart[1]) * $part,
            $depart[2] + ($arrivee[2] - $depart[2]) * $part,
        ]);
    }

    public static function assombrir(string $couleur, float $part): string
    {
        return self::melanger($couleur, '#000000', $part);
    }

    public static function eclaircir(string $couleur, float $part): string
    {
        return self::melanger($couleur, '#ffffff', $part);
    }

    /**
     * Assombrit une couleur juste assez pour être lisible sur un fond donné.
     *
     * Sert au texte coloré — un lien bleu clair sur fond blanc reste illisible ;
     * on descend par petits paliers jusqu'à atteindre le minimum WCAG.
     */
    public static function lisibleSur(string $couleur, string $fond): string
    {
        $candidat = self::normaliser($couleur);
        $versNoir = self::luminance($fond) > 0.5;

        for ($palier = 0; $palier < 20; $palier++) {
            if (self::contrasteSuffisant($candidat, $fond)) {
                return $candidat;
            }

            $candidat = $versNoir
                ? self::assombrir($candidat, 0.1)
                : self::eclaircir($candidat, 0.1);
        }

        return $versNoir ? '#000000' : '#ffffff';
    }
}
