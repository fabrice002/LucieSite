@php
    $nombre = $this->nombre();
@endphp

{{-- Aucun bouton de fermeture, volontairement : voir la note de classe. --}}
<div class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-3">
            <x-filament::icon
                icon="heroicon-o-exclamation-triangle"
                class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"
            />

            <div class="text-sm">
                <p class="font-semibold text-amber-900 dark:text-amber-200">
                    {{ $nombre }} {{ $nombre > 1 ? 'dossiers attendent une décision' : 'dossier attend une décision' }}
                </p>
                <p class="mt-1 text-amber-800 dark:text-amber-300/90">
                    Leur durée de conservation est dépassée. Rien n'a été supprimé, et rien ne le sera
                    sans votre décision — mais ces scans de pièces d'identité restent stockés tant que
                    vous n'avez pas tranché.
                </p>
            </div>
        </div>

        <x-filament::button
            :href="$this->url()"
            tag="a"
            color="warning"
            icon="heroicon-o-arrow-right"
            class="shrink-0 self-start sm:self-center"
        >
            Traiter maintenant
        </x-filament::button>
    </div>
</div>
