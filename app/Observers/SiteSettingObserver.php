<?php

namespace App\Observers;

use App\Models\SiteSetting;
use App\Support\SiteSettingRepository;

class SiteSettingObserver
{
    public function __construct(private readonly SiteSettingRepository $repository) {}

    /**
     * Handle the SiteSetting "saved" event.
     */
    public function saved(SiteSetting $siteSetting): void
    {
        $this->repository->forget();
    }

    /**
     * Handle the SiteSetting "deleted" event.
     */
    public function deleted(SiteSetting $siteSetting): void
    {
        $this->repository->forget();
    }
}
