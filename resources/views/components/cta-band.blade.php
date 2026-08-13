@props([
    'titre' => null,
    'texte' => null,
    'bouton' => 'Déposer mon dossier',
    'url' => null,
])

{{-- Bandeau d'appel à l'action, repris tel quel en bas de chaque page. --}}
@if (filled($titre))
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="rounded-2xl border border-brand-line bg-brand-soft px-6 py-12 text-center sm:px-12">
            <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">
                {{ $titre }}
            </h2>

            @if (filled($texte))
                <p class="mx-auto mt-4 max-w-2xl leading-relaxed text-ink-muted">{{ $texte }}</p>
            @endif

            <a href="{{ $url ?? route('depot.create') }}"
               class="mt-8 inline-block rounded-md bg-brand px-6 py-3 font-medium text-white transition hover:bg-brand-hover">
                {{ $bouton }}
            </a>
        </div>
    </section>
@endif
