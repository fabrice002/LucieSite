@php
    $etapes = $section->liste('etapes');
@endphp

@if ($etapes !== [])
    <section class="border-y border-line bg-surface-muted">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            @if ($titre = $section->valeur('titre'))
                <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">{{ $titre }}</h2>
            @endif

            @if ($introduction = $section->valeur('introduction'))
                <p class="mt-4 max-w-3xl leading-relaxed text-ink-muted">{{ $introduction }}</p>
            @endif

            <ol class="mt-10 grid gap-8 md:grid-cols-3">
                @foreach ($etapes as $index => $etape)
                    <li class="rounded-xl border border-line bg-surface-raised p-6">
                        <span class="flex size-9 items-center justify-center rounded-full bg-brand-soft font-semibold text-brand-text">
                            {{ $index + 1 }}
                        </span>

                        <h3 class="mt-4 font-semibold text-ink-strong">{{ $etape['titre'] ?? '' }}</h3>

                        @if (filled($etape['description'] ?? null))
                            <p class="mt-2 text-sm leading-relaxed text-ink-muted">{{ $etape['description'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif
