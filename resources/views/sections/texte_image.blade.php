@php
    $image = $section->valeur('image');
    $aGauche = $section->valeur('position_image', 'droite') === 'gauche';
@endphp

<section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
    <div class="grid items-center gap-10 md:grid-cols-2">
        <div @class(['md:order-2' => $aGauche])>
            @if ($titre = $section->valeur('titre'))
                <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">{{ $titre }}</h2>
            @endif

            <div class="prose-simple mt-4 leading-relaxed text-ink-muted">
                {!! $section->valeur('texte') !!}
            </div>
        </div>

        @if (filled($image))
            <div @class(['md:order-1' => $aGauche])>
                <x-content-image
                    :chemin="$image"
                    :alt="$section->valeur('image_alt', '')"
                    class="w-full rounded-xl border border-line object-cover"
                />
            </div>
        @endif
    </div>
</section>
