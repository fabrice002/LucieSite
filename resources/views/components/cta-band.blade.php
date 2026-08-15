@props([
    'titre' => null,
    'texte' => null,
    'bouton' => 'Déposer mon dossier',
    'url' => null,
    'secondaire' => true,
])

{{-- Bandeau d'appel à l'action, repris en bas de chaque page.

     Le même bouton dominant partout : un candidat qui hésite doit retrouver la
     même porte d'entrée où qu'il s'arrête de lire. --}}
@if (filled($titre))
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="rounded-2xl border border-brand-line bg-brand-soft px-6 py-12 text-center sm:px-12">
            <h2 class="text-2xl font-bold tracking-tight text-balance text-ink-strong sm:text-3xl">
                {{ $titre }}
            </h2>

            @if (filled($texte))
                <p class="mx-auto mt-4 max-w-2xl leading-relaxed text-ink-muted">{{ $texte }}</p>
            @endif

            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="{{ $url ?? route('depot.create') }}"
                   class="rounded-md bg-brand px-6 py-3 font-medium text-brand-contrast transition hover:bg-brand-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                    {{ $bouton }}
                </a>

                @if ($secondaire)
                    <a href="{{ route('contact') }}"
                       class="rounded-md border border-brand-line bg-surface-raised px-6 py-3 font-medium text-ink transition hover:border-brand hover:text-brand-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                        {{ content('global.cta_secondaire', 'Poser une question') }}
                    </a>
                @endif
            </div>
        </div>
    </section>
@endif
