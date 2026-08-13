@props([
    'titre',
    'introduction' => null,
])

{{-- En-tête commun à toutes les pages vitrines, pour une mise en page uniforme. --}}
<section class="border-b border-line bg-surface-muted">
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
        <h1 class="max-w-3xl text-3xl font-bold tracking-tight text-ink-strong sm:text-4xl">
            {{ $titre }}
        </h1>

        @if (filled($introduction))
            <p class="mt-4 max-w-3xl text-lg leading-relaxed text-ink-muted">
                {{ $introduction }}
            </p>
        @endif
    </div>
</section>
