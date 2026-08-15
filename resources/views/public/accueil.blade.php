@php
    $services = App\Models\Service::publiés();
    $temoignages = App\Models\Testimonial::query()->published()->limit(3)->get();
@endphp

<x-public-layout
    :title="content('accueil.meta_titre', content('global.nom_site'))"
    :description="content('accueil.meta_description')"
>
    {{-- Hero. Un seul appel à l'action dominant, repris dans la navigation et
         en bas de chaque page ; le second reste volontairement discret. --}}
    <section class="border-b border-line bg-gradient-to-b from-brand-soft to-surface">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-balance text-ink-strong sm:text-5xl">
                        {{ content('accueil.hero_titre') }}
                    </h1>

                    <p class="mt-6 text-lg leading-relaxed text-ink-muted">
                        {{ content('accueil.hero_sous_titre') }}
                    </p>

                    <div class="mt-10 flex flex-wrap gap-4">
                        <a href="{{ route('depot.create') }}"
                           class="rounded-md bg-brand px-6 py-3 font-medium text-brand-contrast transition hover:bg-brand-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                            {{ content('accueil.hero_bouton', 'Déposer mon dossier') }}
                        </a>

                        <a href="{{ route('contact') }}"
                           class="rounded-md border border-line bg-surface-raised px-6 py-3 font-medium text-ink transition hover:border-brand hover:text-brand-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                            {{ content('accueil.hero_bouton_secondaire', 'Poser une question') }}
                        </a>
                    </div>

                    @if ($mention = content_filled('accueil.hero_mention'))
                        <p class="mt-6 text-sm text-ink-muted">{{ $mention }}</p>
                    @endif
                </div>

                <x-media-slot
                    :chemin="content_filled('accueil.hero_image')"
                    :alt="content('accueil.hero_image_alt')"
                    class="hidden lg:block"
                />
            </div>
        </div>
    </section>

    {{-- Bande de réassurance. Vide tant que la cliente n'a pas renseigné de
         données réelles : aucun chiffre n'est inventé ici. --}}
    <x-reassurance-band />

    {{-- Notre processus --}}
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">
            {{ content('accueil.section_etapes_titre', 'Comment ça se passe') }}
        </h2>

        @if ($intro = content_filled('accueil.section_etapes_intro'))
            <p class="mt-4 max-w-3xl leading-relaxed text-ink-muted">{{ $intro }}</p>
        @endif

        <x-process-steps bloc="accueil" prefixe="etape" />
    </section>

    {{-- Blocs libres composés depuis le back-office. --}}
    <x-page-sections page="accueil" />

    {{-- Services, en cartes menant chacune à sa page. --}}
    @if ($services->isNotEmpty())
        <section class="border-y border-line bg-surface-muted">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">
                    {{ content('accueil.section_services_titre', 'Nos services') }}
                </h2>

                @if ($intro = content_filled('accueil.section_services_intro'))
                    <p class="mt-4 max-w-3xl leading-relaxed text-ink-muted">{{ $intro }}</p>
                @endif

                <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($services as $service)
                        <x-service-card :service="$service" />
                    @endforeach
                </div>

                <a href="{{ route('services') }}"
                   class="mt-8 inline-block rounded font-medium text-brand-text hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                    {{ content('accueil.section_services_bouton', 'Voir tous nos services') }} &rarr;
                </a>
            </div>
        </section>
    @endif

    {{-- Témoignages. La section disparaît si aucun n'est publié : mieux vaut
         rien qu'une promesse sans preuve. --}}
    @if ($temoignages->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">
                {{ content('accueil.section_temoignages_titre', 'Ils nous ont fait confiance') }}
            </h2>

            <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($temoignages as $temoignage)
                    <x-testimonial-card :temoignage="$temoignage" />
                @endforeach
            </div>

            <a href="{{ route('temoignages') }}"
               class="mt-8 inline-block rounded font-medium text-brand-text hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                {{ content('accueil.section_temoignages_bouton', 'Lire tous les témoignages') }} &rarr;
            </a>
        </section>
    @endif

    <x-cta-band
        :titre="content('accueil.cta_titre')"
        :texte="content('accueil.cta_texte')"
        :bouton="content('accueil.cta_bouton', 'Déposer mon dossier')"
    />
</x-public-layout>
