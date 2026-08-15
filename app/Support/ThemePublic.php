<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Traduit les réglages d'apparence en variables CSS.
 *
 * La cliente ne règle que quatre couleurs ; toutes les nuances qui en découlent
 * — survol, fond doux, filet, texte coloré lisible — sont calculées ici, pour
 * les deux thèmes. Lui demander de choisir douze couleurs cohérentes serait lui
 * demander de faire un métier qui n'est pas le sien.
 *
 * Rien n'est généré côté Tailwind : ces variables surchargent simplement les
 * jetons déclarés dans resources/css/public.css. Une classe Tailwind construite
 * dynamiquement (bg-{{ $couleur }}) n'existerait jamais dans le CSS compilé.
 */
class ThemePublic
{
    /**
     * Le fond des pages, clair et sombre — repris de public.css.
     */
    private const FOND_CLAIR = '#ffffff';

    private const FOND_SOMBRE = '#0f172a';

    /**
     * Les variables du thème clair.
     *
     * @return array<string, string>
     */
    public function variablesClaires(): array
    {
        $principale = $this->couleur('couleur_principale');
        $secondaire = $this->couleur('couleur_secondaire');
        $accent = $this->couleur('couleur_accent');
        $surPrincipale = $this->couleur('couleur_texte_sur_principale');

        return [
            '--color-brand' => $principale,
            '--color-brand-hover' => Couleur::assombrir($principale, 0.15),
            '--color-brand-text' => Couleur::lisibleSur($principale, self::FOND_CLAIR),
            '--color-brand-soft' => Couleur::eclaircir($principale, 0.92),
            '--color-brand-line' => Couleur::eclaircir($principale, 0.72),
            '--color-brand-contrast' => $surPrincipale,

            '--color-secondary' => $secondaire,
            '--color-secondary-text' => Couleur::lisibleSur($secondaire, self::FOND_CLAIR),
            '--color-secondary-soft' => Couleur::eclaircir($secondaire, 0.92),

            '--color-accent' => $accent,
            '--color-accent-text' => Couleur::lisibleSur($accent, self::FOND_CLAIR),
            '--color-accent-soft' => Couleur::eclaircir($accent, 0.92),

            '--font-sans' => $this->pileDePolice(),
        ];
    }

    /**
     * Les variables du thème sombre.
     *
     * Les mêmes couleurs y seraient trop denses : sur fond sombre, on éclaircit
     * au lieu d'assombrir, et le texte coloré est remonté jusqu'à redevenir
     * lisible.
     *
     * @return array<string, string>
     */
    public function variablesSombres(): array
    {
        $principale = $this->couleur('couleur_principale');
        $secondaire = $this->couleur('couleur_secondaire');
        $accent = $this->couleur('couleur_accent');

        return [
            '--color-brand' => Couleur::eclaircir($principale, 0.15),
            '--color-brand-hover' => Couleur::eclaircir($principale, 0.32),
            '--color-brand-text' => Couleur::lisibleSur($principale, self::FOND_SOMBRE),
            '--color-brand-soft' => Couleur::assombrir($principale, 0.6),
            '--color-brand-line' => Couleur::assombrir($principale, 0.25),

            '--color-secondary' => Couleur::eclaircir($secondaire, 0.15),
            '--color-secondary-text' => Couleur::lisibleSur($secondaire, self::FOND_SOMBRE),
            '--color-secondary-soft' => Couleur::assombrir($secondaire, 0.6),

            '--color-accent' => Couleur::eclaircir($accent, 0.15),
            '--color-accent-text' => Couleur::lisibleSur($accent, self::FOND_SOMBRE),
            '--color-accent-soft' => Couleur::assombrir($accent, 0.6),
        ];
    }

    /**
     * La feuille de style à injecter, prête à poser dans le <head>.
     */
    public function css(): string
    {
        $regles = ':root{'.$this->declarations($this->variablesClaires()).'}';

        if ($this->themeSombreActif()) {
            $regles .= '.dark{'.$this->declarations($this->variablesSombres()).'}';
        }

        return $regles;
    }

    /**
     * L'alias de la police retenue, tel que Vite le connaît.
     *
     * Le repli compte : une police retirée de vite.config.js ne serait plus
     * servie, et @fonts lèverait une exception sur un alias inconnu — toutes
     * les pages publiques tomberaient d'un coup.
     */
    public function police(): string
    {
        $autorisees = $this->policesAutorisees();

        $choisie = setting('police');

        return is_string($choisie) && array_key_exists($choisie, $autorisees)
            ? $choisie
            : (string) array_key_first($autorisees);
    }

    /**
     * Le nom lisible de la police retenue, celui écrit dans la CSS.
     */
    public function familleDePolice(): string
    {
        $autorisees = $this->policesAutorisees();

        return (string) ($autorisees[$this->police()]['famille'] ?? 'Instrument Sans');
    }

    /**
     * @return array<string, array{famille: string, description: string, repli?: string}>
     */
    private function policesAutorisees(): array
    {
        /** @var array<string, array{famille: string, description: string, repli?: string}> */
        return config('brand.polices', []);
    }

    /**
     * Le thème sombre est-il proposé au public ?
     */
    public function themeSombreActif(): bool
    {
        return (bool) setting('theme_sombre_actif', true);
    }

    /**
     * L'adresse du logo téléversé, clair ou sombre.
     *
     * Le repli suit l'ordre historique : réglage du back-office, puis
     * config/brand.php (la variable BRAND_LOGO), puis rien — auquel cas le
     * monogramme prend le relais.
     */
    public function urlLogo(string $variante = 'clair'): ?string
    {
        $televerse = setting('logo_'.$variante);

        if (is_string($televerse) && $televerse !== '') {
            return Storage::disk('public')->url($televerse);
        }

        if ($variante !== 'clair') {
            return null;
        }

        $configure = config('brand.logo');

        return is_string($configure) && $configure !== '' ? asset($configure) : null;
    }

    /**
     * L'adresse du favicon téléversé, s'il y en a un.
     */
    public function urlFavicon(): ?string
    {
        $televerse = setting('favicon');

        return is_string($televerse) && $televerse !== ''
            ? Storage::disk('public')->url($televerse)
            : null;
    }

    /**
     * La pile de polices complète, avec ses replis système.
     */
    private function pileDePolice(): string
    {
        $repli = $this->policesAutorisees()[$this->police()]['repli'] ?? 'sans-serif';

        // Le repli suit la nature de la police choisie : une sérif qui
        // retomberait sur une linéale changerait l'allure de la page dès que le
        // fichier n'arrive pas, ce qui est courant en 3G.
        $systeme = $repli === 'serif'
            ? 'ui-serif, Georgia, Cambria, serif'
            : 'ui-sans-serif, system-ui, sans-serif';

        return "'".$this->familleDePolice()."', ".$systeme.", 'Apple Color Emoji', 'Segoe UI Emoji'";
    }

    /**
     * Lit une couleur réglée, en retombant sur le repli si elle est invalide.
     *
     * Une valeur saisie à la main peut être vide ou mal formée ; mieux vaut la
     * couleur livrée qu'une déclaration CSS cassée qui emporterait tout le bloc.
     */
    private function couleur(string $cle): string
    {
        $valeur = setting($cle);

        if (Couleur::estValide(is_string($valeur) ? $valeur : null)) {
            return Couleur::normaliser((string) $valeur);
        }

        /** @var string $repli */
        $repli = config('brand.apparence.'.$cle, '#1d4ed8');

        return Couleur::normaliser($repli);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function declarations(array $variables): string
    {
        $declarations = '';

        foreach ($variables as $nom => $valeur) {
            $declarations .= $nom.':'.$valeur.';';
        }

        return $declarations;
    }
}
