<?php

namespace App\Support;

use App\Models\SiteContent;
use Illuminate\Support\Facades\Cache;

/**
 * Accès en lecture, mis en cache, aux textes éditables des pages publiques.
 *
 * Le cache est invalidé par SiteContentObserver à chaque sauvegarde, de sorte
 * qu'une modification faite dans le back-office est visible immédiatement.
 */
class SiteContentRepository
{
    /**
     * Prefix used for every cache entry handled by this repository.
     */
    public const CACHE_PREFIX = 'site_content';

    /**
     * Resolve a single text, addressed as "bloc.cle".
     */
    public function get(string $path, ?string $default = null): string
    {
        if (! str_contains($path, '.')) {
            return $default ?? '';
        }

        [$key, $field] = explode('.', $path, 2);

        $value = $this->block($key)[$field] ?? null;

        return is_string($value) && $value !== ''
            ? $value
            : ($default ?? '');
    }

    /**
     * Get every text of a block for the given locale.
     *
     * @return array<string, string>
     */
    public function block(string $key, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        /** @var array<string, string> */
        return Cache::rememberForever(
            self::cacheKey($key, $locale),
            function () use ($key, $locale): array {
                $content = SiteContent::query()
                    ->where('key', $key)
                    ->where('locale', $locale)
                    ->first()?->content;

                return is_array($content) ? $content : [];
            },
        );
    }

    /**
     * Build the cache key of a block.
     */
    public static function cacheKey(string $key, string $locale): string
    {
        return self::CACHE_PREFIX.":{$locale}:{$key}";
    }

    /**
     * Drop the cached texts of a block, for every known locale.
     */
    public function forget(string $key): void
    {
        foreach (self::locales() as $locale) {
            Cache::forget(self::cacheKey($key, $locale));
        }
    }

    /**
     * Get every locale a block may have been cached under.
     *
     * @return list<string>
     */
    private static function locales(): array
    {
        /** @var list<string> $locales */
        $locales = SiteContent::query()
            ->distinct()
            ->pluck('locale')
            ->push(app()->getLocale())
            ->push(config('app.fallback_locale'))
            ->filter(fn (mixed $locale): bool => is_string($locale) && $locale !== '')
            ->unique()
            ->values()
            ->all();

        return $locales;
    }
}
