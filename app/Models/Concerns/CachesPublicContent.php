<?php

namespace App\Models\Concerns;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Met en cache le contenu public d'un modèle, et l'invalide à chaque écriture.
 *
 * Même principe que SiteContentObserver : une modification faite dans le
 * back-office doit être visible immédiatement sur le site, sans commande à
 * lancer ni cache à vider à la main.
 */
trait CachesPublicContent
{
    /**
     * Vide le cache dès qu'un enregistrement est créé, modifié ou supprimé.
     */
    public static function bootCachesPublicContent(): void
    {
        foreach (['saved', 'deleted'] as $evenement) {
            static::registerModelEvent($evenement, fn () => static::forgetPublicCache());
        }
    }

    /**
     * Clé de cache du contenu publié de ce modèle.
     */
    public static function publicCacheKey(): string
    {
        return 'public:'.str(class_basename(static::class))->snake()->plural()->value();
    }

    public static function forgetPublicCache(): void
    {
        Cache::forget(static::publicCacheKey());
    }

    /**
     * Mémorise des données déjà réduites en tableaux.
     *
     * Rien d'autre que des tableaux et des scalaires ne doit transiter par le
     * cache. Laravel fixe `cache.serializable_classes` à false : aucun objet
     * PHP n'est désérialisé depuis le cache, ce qui ferme les chaînes de
     * gadgets si l'APP_KEY venait à fuiter. Un modèle Eloquent mis en cache
     * reviendrait en __PHP_Incomplete_Class et casserait la page — on ne
     * contourne pas cette protection, on réhydrate à la lecture.
     *
     * Chaque modèle réduit donc sa requête en tableaux d'attributs bruts —
     * getAttributes(), soit les valeurs telles qu'elles sont en base, avant
     * tout cast — puis les repasse par hydrate() à la lecture.
     *
     * @param  Closure(): mixed  $resolveur
     */
    protected static function rememberPublic(Closure $resolveur): mixed
    {
        return Cache::rememberForever(static::publicCacheKey(), $resolveur);
    }
}
