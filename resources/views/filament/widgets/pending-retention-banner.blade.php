@php
    $nombre = $this->nombre();
@endphp

{{-- Aucun bouton de fermeture, volontairement : voir la note de classe. --}}
<div class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-3">
            <svg class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400"
                 viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.19-1.458-1.516-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
            </svg>

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

        <a href="{{ $this->url() }}"
           class="shrink-0 self-start rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-amber-700 sm:self-center">
            Traiter maintenant
        </a>
    </div>
</div>
