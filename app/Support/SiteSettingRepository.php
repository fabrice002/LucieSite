<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Accès en lecture et en écriture aux réglages d'apparence.
 *
 * Trois niveaux de repli, dans cet ordre : la valeur enregistrée en base, le
 * défaut passé à l'appel, puis config/brand.php. Une table vide donne donc
 * exactement le site tel qu'il est livré — rien ne casse tant que la cliente
 * n'a rien réglé.
 *
 * Le cache est invalidé par SiteSettingObserver à chaque sauvegarde.
 */
class SiteSettingRepository
{
    public const CACHE_KEY = 'site_settings';

    /**
     * Lit un réglage.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $valeur = $this->all()[$key] ?? null;

        if ($valeur !== null && $valeur !== '') {
            return $this->convertir($key, $valeur);
        }

        return $default ?? config('brand.apparence.'.$key);
    }

    /**
     * Tous les réglages enregistrés, bruts, mis en cache.
     *
     * Seuls des tableaux de chaînes transitent par le cache — voir la note de
     * CachesPublicContent : Laravel n'y désérialise aucun objet.
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        /** @var array<string, string|null> */
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => SiteSetting::query()->pluck('value', 'key')->all(),
        );
    }

    /**
     * Les types déclarés, indexés par clé.
     *
     * @return array<string, string>
     */
    public function types(): array
    {
        /** @var array<string, string> */
        return Cache::rememberForever(
            self::CACHE_KEY.':types',
            fn (): array => SiteSetting::query()->pluck('type', 'key')->all(),
        );
    }

    /**
     * Enregistre un réglage, en créant la ligne au besoin.
     */
    public function set(string $key, mixed $value, string $type = SiteSetting::TYPE_TEXTE): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => match (true) {
                    is_bool($value) => $value ? '1' : '0',
                    $value === null => null,
                    default => (string) $value,
                },
                'type' => $type,
            ],
        );
    }

    /**
     * Enregistre plusieurs réglages d'un coup.
     *
     * @param  array<string, mixed>  $valeurs
     * @param  array<string, string>  $types
     */
    public function setMany(array $valeurs, array $types = []): void
    {
        foreach ($valeurs as $key => $value) {
            $this->set($key, $value, $types[$key] ?? $this->typeParDefaut($key));
        }
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.':types');
    }

    /**
     * Convertit une valeur stockée selon la nature du réglage.
     */
    private function convertir(string $key, string $valeur): mixed
    {
        return match ($this->types()[$key] ?? $this->typeParDefaut($key)) {
            SiteSetting::TYPE_BOOLEEN => filter_var($valeur, FILTER_VALIDATE_BOOLEAN),
            default => $valeur,
        };
    }

    /**
     * Devine la nature d'un réglage d'après son nom.
     *
     * Évite d'avoir à répéter le type à chaque appel : « couleur_… » est une
     * couleur, « … _actif » un booléen, « logo_… » une image.
     */
    private function typeParDefaut(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'couleur_') => SiteSetting::TYPE_COULEUR,
            str_ends_with($key, '_actif') => SiteSetting::TYPE_BOOLEEN,
            str_starts_with($key, 'logo_'), $key === 'favicon' => SiteSetting::TYPE_IMAGE,
            default => SiteSetting::TYPE_TEXTE,
        };
    }
}
