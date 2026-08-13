<?php

namespace App\Observers;

use App\Models\SiteContent;
use App\Support\SiteContentRepository;
use Illuminate\Support\Facades\Cache;

class SiteContentObserver
{
    public function __construct(private readonly SiteContentRepository $repository) {}

    /**
     * Handle the SiteContent "saved" event.
     */
    public function saved(SiteContent $siteContent): void
    {
        $this->flush($siteContent);
    }

    /**
     * Handle the SiteContent "deleted" event.
     */
    public function deleted(SiteContent $siteContent): void
    {
        $this->flush($siteContent);
    }

    /**
     * Invalidate the cached texts of the block, including its previous key
     * when the key itself has just been changed.
     */
    private function flush(SiteContent $siteContent): void
    {
        $this->repository->forget($siteContent->key);

        $originalKey = $siteContent->getOriginal('key');

        if (is_string($originalKey) && $originalKey !== $siteContent->key) {
            $this->repository->forget($originalKey);
        }

        $originalLocale = $siteContent->getOriginal('locale');

        if (is_string($originalLocale)) {
            Cache::forget(SiteContentRepository::cacheKey($siteContent->key, $originalLocale));
        }
    }
}
