@php
    $inclus = $service->inclus();
    $exclus = $service->exclus();
@endphp

<x-public-layout
    :title="$service->meta_title ?: $service->title.' — '.content('global.nom_site')"
    :description="$service->meta_description ?: $service->summary"
>
    <x-page-header :titre="$service->title" :introduction="$service->summary" />

    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <x-media-slot
            :chemin="$service->image_path"
            :alt="$service->image_alt ?? $service->title"
            class="mb-10 border border-line"
        />

        @if (filled($service->body))
            <div class="prose-simple leading-relaxed text-ink-muted">
                {!! $service->body !!}
            </div>
        @endif

        {{-- Périmètre de la prestation.

             Dire explicitement ce qui n'est pas compris est aussi important que
             lister ce qui l'est : le flou sur le périmètre est le principal
             terrain des litiges dans ce secteur, et il protège autant le
             candidat que le cabinet. --}}
        @if ($inclus !== [] || $exclus !== [])
            <section class="mt-12 rounded-xl border border-line bg-surface-muted p-6 sm:p-8">
                <h2 class="text-lg font-semibold text-ink-strong">
                    {{ content('services.perimetre_titre', 'Ce que comprend cet accompagnement') }}
                </h2>

                <div class="mt-6 grid gap-8 sm:grid-cols-2">
                    @if ($inclus !== [])
                        <div>
                            <h3 class="text-sm font-semibold text-ink-strong">
                                {{ content('services.inclus_titre', 'Compris dans la prestation') }}
                            </h3>

                            <ul class="mt-3 space-y-2 text-sm text-ink-muted">
                                @foreach ($inclus as $ligne)
                                    <li class="flex gap-2">
                                        <svg class="mt-0.5 size-4 shrink-0 text-ok" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                                        </svg>
                                        <span>{{ $ligne }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($exclus !== [])
                        <div>
                            <h3 class="text-sm font-semibold text-ink-strong">
                                {{ content('services.exclus_titre', 'Non compris') }}
                            </h3>

                            <ul class="mt-3 space-y-2 text-sm text-ink-muted">
                                @foreach ($exclus as $ligne)
                                    <li class="flex gap-2">
                                        <svg class="mt-0.5 size-4 shrink-0 text-ink-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M4 10a1 1 0 0 1 1-1h10a1 1 0 1 1 0 2H5a1 1 0 0 1-1-1Z" clip-rule="evenodd" />
                                        </svg>
                                        <span>{{ $ligne }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                @if (filled($service->price_note))
                    <p class="mt-6 border-t border-line pt-4 text-sm text-ink">
                        <span class="font-medium text-ink-strong">{{ content('services.tarif_titre', 'Tarif') }} :</span>
                        {{ $service->price_note }}
                    </p>
                @endif
            </section>
        @endif

        <nav class="mt-12 border-t border-line pt-6">
            <a href="{{ route('services') }}"
               class="rounded text-sm font-medium text-brand-text hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                &larr; {{ content('services.retour', 'Tous nos services') }}
            </a>
        </nav>
    </article>

    <x-cta-band
        :titre="content('services.cta_titre')"
        :bouton="content('services.cta_bouton', 'Déposer mon dossier')"
        :url="route('depot.create')"
    />
</x-public-layout>
