@php
    $logos = $section->liste('logos');
@endphp

@if ($logos !== [])
    <section class="border-y border-line bg-surface-muted">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
            @if ($titre = $section->valeur('titre'))
                <h2 class="text-center text-sm font-semibold tracking-wide text-ink-muted uppercase">{{ $titre }}</h2>
            @endif

            <ul class="mt-8 flex flex-wrap items-center justify-center gap-x-10 gap-y-6">
                @foreach ($logos as $logo)
                    <li>
                        <{{ filled($logo['lien'] ?? null) ? 'a' : 'span' }}
                            @if (filled($logo['lien'] ?? null)) href="{{ $logo['lien'] }}" rel="noopener" @endif
                            class="flex flex-col items-center gap-2">

                            <x-content-image
                                :chemin="$logo['image'] ?? null"
                                :alt="$logo['image_alt'] ?? ''"
                                :largeur="200"
                                :hauteur="80"
                                class="h-10 w-auto object-contain"
                            />

                            @if (filled($logo['legende'] ?? null))
                                <span class="text-xs text-ink-muted">{{ $logo['legende'] }}</span>
                            @endif
                        </{{ filled($logo['lien'] ?? null) ? 'a' : 'span' }}>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
