@php
    $cartes = $section->liste('cartes');
@endphp

@if ($cartes !== [])
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        @if ($titre = $section->valeur('titre'))
            <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">{{ $titre }}</h2>
        @endif

        @if ($introduction = $section->valeur('introduction'))
            <p class="mt-4 max-w-3xl leading-relaxed text-ink-muted">{{ $introduction }}</p>
        @endif

        <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($cartes as $carte)
                @php $lien = $carte['lien'] ?? null; @endphp

                <{{ filled($lien) ? 'a' : 'div' }}
                    @if (filled($lien)) href="{{ $lien }}" @endif
                    class="flex flex-col overflow-hidden rounded-xl border border-line bg-surface-raised transition @if (filled($lien)) hover:border-brand @endif">

                    @if (filled($carte['image'] ?? null))
                        <x-content-image
                            :chemin="$carte['image']"
                            :alt="$carte['image_alt'] ?? ''"
                            :hauteur="450"
                            class="h-40 w-full object-cover"
                        />
                    @endif

                    <div class="flex flex-1 flex-col p-6">
                        <h3 class="font-semibold text-ink-strong">{{ $carte['titre'] ?? '' }}</h3>

                        @if (filled($carte['texte'] ?? null))
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-ink-muted">{{ $carte['texte'] }}</p>
                        @endif
                    </div>
                </{{ filled($lien) ? 'a' : 'div' }}>
            @endforeach
        </div>
    </section>
@endif
