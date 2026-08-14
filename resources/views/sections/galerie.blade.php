@php
    $images = $section->liste('images');
@endphp

@if ($images !== [])
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        @if ($titre = $section->valeur('titre'))
            <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">{{ $titre }}</h2>
        @endif

        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($images as $image)
                <figure class="overflow-hidden rounded-xl border border-line bg-surface-raised">
                    <x-content-image
                        :chemin="$image['image'] ?? null"
                        :alt="$image['image_alt'] ?? ''"
                        :hauteur="900"
                        class="h-52 w-full object-cover"
                    />

                    @if (filled($image['legende'] ?? null))
                        <figcaption class="px-4 py-3 text-sm text-ink-muted">{{ $image['legende'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </section>
@endif
