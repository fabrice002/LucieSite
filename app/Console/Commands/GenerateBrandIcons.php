<?php

namespace App\Console\Commands;

use GdImage;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Régénère les icônes d'onglet à partir de la marque définie dans config/brand.php.
 *
 * Le navigateur ne sait pas afficher un composant Blade dans son onglet : il lui
 * faut de vrais fichiers. Cette commande les produit depuis les mêmes
 * coordonnées que le logo des pages, pour que la marque reste identique partout.
 */
class GenerateBrandIcons extends Command
{
    protected $signature = 'ln:generate-icons';

    protected $description = 'Régénère favicon.svg, favicon.ico et apple-touch-icon.png depuis config/brand.php';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('L\'extension PHP « gd » est requise pour générer les icônes.');

            return self::FAILURE;
        }

        $this->ecrireSvg();
        $this->ecrirePng(180, public_path('apple-touch-icon.png'));
        // Trois tailles dans le même fichier : le navigateur choisit la sienne.
        $this->ecrireIco([16, 32, 48], public_path('favicon.ico'));

        $this->newLine();
        $this->info('Icônes régénérées :');
        $this->line('  public/favicon.svg');
        $this->line('  public/favicon.ico');
        $this->line('  public/apple-touch-icon.png');
        $this->newLine();
        $this->comment('Videz le cache de votre navigateur pour voir le changement dans l\'onglet.');

        return self::SUCCESS;
    }

    /**
     * Le SVG, préféré par les navigateurs modernes car net à toute taille.
     */
    private function ecrireSvg(): void
    {
        $fond = (string) config('brand.icone_fond', '#1e40af');
        $trait = (string) config('brand.icone_trait', '#ffffff');

        $tracés = '';

        foreach ($this->monogramme() as $lettre) {
            $points = implode(' ', array_map(
                fn (array $point): string => implode(',', $point),
                $lettre,
            ));

            $tracés .= "\n    <polygon fill=\"{$trait}\" points=\"{$points}\" />";
        }

        file_put_contents(public_path('favicon.svg'), <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" width="40" height="40">
                <rect width="40" height="40" rx="8" fill="{$fond}" />{$tracés}
            </svg>

            SVG);
    }

    /**
     * @param  int<1, max>  $taille
     */
    private function ecrirePng(int $taille, string $destination): void
    {
        imagepng($this->dessiner($taille), $destination);
    }

    /**
     * Un fichier .ico contenant plusieurs tailles, chacune encodée en PNG.
     *
     * @param  list<int<1, 255>>  $tailles
     */
    private function ecrireIco(array $tailles, string $destination): void
    {
        $images = [];

        foreach ($tailles as $taille) {
            ob_start();
            imagepng($this->dessiner($taille));
            $images[$taille] = (string) ob_get_clean();
        }

        // En-tête ICONDIR : réservé, type 1 (icône), nombre d'images.
        $ico = pack('vvv', 0, 1, count($images));

        // Chaque entrée fait 16 octets ; les données suivent toutes les entrées.
        $offset = 6 + (16 * count($images));

        // Le format ICO code la dimension sur un octet, d'où la limite à 255.
        foreach ($images as $taille => $donnees) {
            $ico .= pack('CCCCvvVV', $taille, $taille, 0, 0, 1, 32, strlen($donnees), $offset);

            $offset += strlen($donnees);
        }

        file_put_contents($destination, $ico.implode('', $images));
    }

    /**
     * Dessine la marque : fond arrondi, monogramme par-dessus.
     *
     * @param  int<1, max>  $taille
     */
    private function dessiner(int $taille): GdImage
    {
        $image = imagecreatetruecolor($taille, $taille);

        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, $this->allouerTransparent($image));
        imagealphablending($image, true);
        imageantialias($image, true);

        $fond = $this->allouer($image, (string) config('brand.icone_fond', '#1e40af'));
        $trait = $this->allouer($image, (string) config('brand.icone_trait', '#ffffff'));

        $this->fondArrondi($image, $taille, (int) round($taille * 0.2), $fond);

        // Les coordonnées de config/brand.php sont sur une grille de 40.
        $echelle = $taille / 40;

        foreach ($this->monogramme() as $lettre) {
            $points = [];

            foreach ($lettre as [$x, $y]) {
                $points[] = (int) round($x * $echelle);
                $points[] = (int) round($y * $echelle);
            }

            imagefilledpolygon($image, $points, $trait);
        }

        return $image;
    }

    private function fondArrondi(GdImage $image, int $taille, int $rayon, int $couleur): void
    {
        imagefilledrectangle($image, $rayon, 0, $taille - $rayon - 1, $taille - 1, $couleur);
        imagefilledrectangle($image, 0, $rayon, $taille - 1, $taille - $rayon - 1, $couleur);

        $diametre = $rayon * 2;

        foreach ([[$rayon, $rayon], [$taille - $rayon - 1, $rayon], [$rayon, $taille - $rayon - 1], [$taille - $rayon - 1, $taille - $rayon - 1]] as [$cx, $cy]) {
            imagefilledellipse($image, $cx, $cy, $diametre, $diametre, $couleur);
        }
    }

    /**
     * @return list<list<array{0: float, 1: float}>>
     */
    private function monogramme(): array
    {
        /** @var array<string, list<array{0: float, 1: float}>> $monogramme */
        $monogramme = config('brand.monogramme', []);

        throw_if($monogramme === [], new RuntimeException('Aucun monogramme défini dans config/brand.php.'));

        return array_values($monogramme);
    }

    /**
     * Alloue une couleur à partir d'un code hexadécimal.
     */
    private function allouer(GdImage $image, string $hex): int
    {
        [$rouge, $vert, $bleu] = $this->couleur($hex);

        $couleur = imagecolorallocate($image, $rouge, $vert, $bleu);

        throw_if($couleur === false, new RuntimeException("Couleur « {$hex} » inutilisable."));

        return $couleur;
    }

    private function allouerTransparent(GdImage $image): int
    {
        $couleur = imagecolorallocatealpha($image, 0, 0, 0, 127);

        throw_if($couleur === false, new RuntimeException('Transparence indisponible.'));

        return $couleur;
    }

    /**
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    private function couleur(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        /** @var array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>} */
        return array_map(
            fn (string $composante): int => max(0, min(255, (int) hexdec($composante))),
            [substr($hex, 0, 2), substr($hex, 2, 2), substr($hex, 4, 2)],
        );
    }
}
