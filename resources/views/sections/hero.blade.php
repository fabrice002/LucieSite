@php
    $image = $section->valeur('image');
@endphp

<section class="relative overflow-hidden border-b border-line bg-surface-muted">
    @if (filled($image))
        <x-content-image
            :chemin="$image"
            :alt="$section->valeur('image_alt', '')"
            class="absolute inset-0 size-full object-cover opacity-15"
        />
    @endif

    <div class="relative mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
        <div class="max-w-3xl">
            @if ($surTitre = $section->valeur('sur_titre'))
                <p class="text-sm font-semibold tracking-wide text-brand-text uppercase">{{ $surTitre }}</p>
            @endif

            <h1 class="mt-3 text-3xl font-bold tracking-tight text-ink-strong sm:text-5xl">
                {{ $section->valeur('titre') }}
            </h1>

            @if ($texte = $section->valeur('texte'))
                <p class="mt-6 text-lg leading-relaxed text-ink-muted">{{ $texte }}</p>
            @endif

            @if ($section->valeur('bouton_libelle') || $section->valeur('bouton2_libelle'))
                <div class="mt-10 flex flex-wrap gap-4">
                    @if ($libelle = $section->valeur('bouton_libelle'))
                        <a href="{{ $section->valeur('bouton_url', '#') }}"
                           class="rounded-md bg-brand px-6 py-3 font-medium text-white transition hover:bg-brand-hover">
                            {{ $libelle }}
                        </a>
                    @endif

                    @if ($libelle = $section->valeur('bouton2_libelle'))
                        <a href="{{ $section->valeur('bouton2_url', '#') }}"
                           class="rounded-md border border-brand-line bg-surface-raised px-6 py-3 font-medium text-brand-text transition hover:border-brand">
                            {{ $libelle }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
