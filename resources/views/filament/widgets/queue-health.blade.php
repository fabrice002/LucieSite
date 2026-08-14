<div role="alert"
     class="rounded-xl border border-danger-300 bg-danger-50 p-4 dark:border-danger-700 dark:bg-danger-500/10">
    <div class="flex items-start gap-3">
        <x-filament::icon icon="heroicon-o-exclamation-triangle"
                          class="mt-0.5 size-6 shrink-0 text-danger-600 dark:text-danger-400" />

        <div>
            <p class="font-semibold text-danger-800 dark:text-danger-300">
                Les e-mails ne partent pas : le worker de file d'attente semble arrêté.
            </p>

            <p class="mt-1 text-sm text-danger-700 dark:text-danger-400">
                {{ $enAttente }} message(s) en attente, le plus ancien depuis
                {{ $depuis?->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}.
                Les candidats prévenus depuis ce moment n'ont rien reçu.
            </p>

            <p class="mt-2 font-mono text-sm text-danger-800 dark:text-danger-300">
                php artisan queue:work
            </p>
        </div>
    </div>
</div>
