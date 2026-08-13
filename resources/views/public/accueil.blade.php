<x-public-layout
    :title="content('accueil.meta_titre', content('global.nom_site'))"
    :description="content('accueil.meta_description')"
>
    {{-- Hero --}}
    <section class="border-b border-line bg-gradient-to-b from-brand-soft to-surface">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
            <div class="max-w-3xl">
                <h1 class="text-3xl font-bold tracking-tight text-ink-strong sm:text-5xl">
                    {{ content('accueil.hero_titre') }}
                </h1>

                <p class="mt-6 text-lg leading-relaxed text-ink-muted">
                    {{ content('accueil.hero_sous_titre') }}
                </p>

                <div class="mt-10 flex flex-wrap gap-4">
                    <a href="{{ route('depot.create') }}"
                       class="rounded-md bg-brand px-6 py-3 font-medium text-white transition hover:bg-brand-hover">
                        {{ content('accueil.hero_bouton', 'Déposer mon dossier') }}
                    </a>

                    <a href="{{ route('suivi.index') }}"
                       class="rounded-md border border-line bg-surface-raised px-6 py-3 font-medium text-ink transition hover:border-brand hover:text-brand-text">
                        {{ content('accueil.hero_bouton_secondaire', 'Suivre mon dossier') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Étapes --}}
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">
            {{ content('accueil.section_etapes_titre', 'Comment ça se passe') }}
        </h2>

        <ol class="mt-10 grid gap-6 md:grid-cols-3">
            @foreach ([1, 2, 3] as $etape)
                <li class="rounded-xl border border-line bg-surface-raised p-6">
                    <span class="flex size-10 items-center justify-center rounded-full bg-brand-soft font-semibold text-brand-text">
                        {{ $etape }}
                    </span>

                    <h3 class="mt-4 font-semibold text-ink-strong">
                        {{ content("accueil.etape_{$etape}_titre") }}
                    </h3>

                    <p class="mt-2 text-sm leading-relaxed text-ink-muted">
                        {{ content("accueil.etape_{$etape}_texte") }}
                    </p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Services --}}
    <section class="border-y border-line bg-surface-muted">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">
                {{ content('accueil.section_services_titre', 'Nos services') }}
            </h2>

            <p class="mt-4 max-w-3xl leading-relaxed text-ink-muted">
                {{ content('accueil.section_services_intro') }}
            </p>

            <a href="{{ route('services') }}"
               class="mt-6 inline-block font-medium text-brand-text hover:underline">
                {{ content('accueil.section_services_bouton', 'Voir tous nos services') }} &rarr;
            </a>
        </div>
    </section>

    {{-- Témoignages --}}
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">
            {{ content('accueil.section_temoignages_titre', 'Ils nous ont fait confiance') }}
        </h2>

        <a href="{{ route('temoignages') }}"
           class="mt-6 inline-block font-medium text-brand-text hover:underline">
            {{ content('accueil.section_temoignages_bouton', 'Lire tous les témoignages') }} &rarr;
        </a>
    </section>

    <x-cta-band
        :titre="content('accueil.cta_titre')"
        :texte="content('accueil.cta_texte')"
        :bouton="content('accueil.cta_bouton', 'Commencer maintenant')"
    />
</x-public-layout>
