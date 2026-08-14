<section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
    <div class="rounded-2xl border border-brand-line bg-brand-soft px-6 py-12 text-center sm:px-12">
        <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">
            {{ $section->valeur('titre') }}
        </h2>

        @if ($texte = $section->valeur('texte'))
            <p class="mx-auto mt-4 max-w-2xl leading-relaxed text-ink-muted">{{ $texte }}</p>
        @endif

        @if ($libelle = $section->valeur('bouton_libelle'))
            <a href="{{ $section->valeur('bouton_url', '#') }}"
               class="mt-8 inline-block rounded-md bg-brand px-6 py-3 font-medium text-white transition hover:bg-brand-hover">
                {{ $libelle }}
            </a>
        @endif
    </div>
</section>
