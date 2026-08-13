<x-public-layout
    :title="content('accueil.meta_titre', content('global.nom_site'))"
    :description="content('accueil.meta_description')"
>
    {{-- Hero --}}
    <section class="bg-gradient-to-b from-brand-50 to-white">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
            <div class="max-w-3xl">
                <h1 class="text-3xl font-bold tracking-tight text-brand-900 sm:text-5xl">
                    {{ content('accueil.hero_titre') }}
                </h1>

                <p class="mt-6 text-lg leading-relaxed text-slate-600">
                    {{ content('accueil.hero_sous_titre') }}
                </p>

                <div class="mt-10 flex flex-wrap gap-4">
                    <a href="{{ route('depot.create') }}"
                       class="rounded-md bg-brand-700 px-6 py-3 font-medium text-white transition hover:bg-brand-800">
                        {{ content('accueil.hero_bouton', 'Déposer mon dossier') }}
                    </a>

                    <a href="{{ route('suivi.index') }}"
                       class="rounded-md border border-brand-200 bg-white px-6 py-3 font-medium text-brand-700 transition hover:border-brand-600">
                        {{ content('accueil.hero_bouton_secondaire', 'Suivre mon dossier') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Étapes --}}
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-2xl font-bold tracking-tight text-brand-900 sm:text-3xl">
            {{ content('accueil.section_etapes_titre', 'Comment ça se passe') }}
        </h2>

        <ol class="mt-10 grid gap-8 md:grid-cols-3">
            @foreach ([1, 2, 3] as $etape)
                <li class="rounded-lg border border-slate-200 p-6">
                    <span class="flex size-9 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-700">
                        {{ $etape }}
                    </span>

                    <h3 class="mt-4 font-semibold text-slate-900">
                        {{ content("accueil.etape_{$etape}_titre") }}
                    </h3>

                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        {{ content("accueil.etape_{$etape}_texte") }}
                    </p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Services : le détail arrive en Phase 5 avec la page dédiée. --}}
    <section class="border-y border-slate-200 bg-slate-50">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-brand-900 sm:text-3xl">
                {{ content('accueil.section_services_titre', 'Nos services') }}
            </h2>

            <p class="mt-4 max-w-3xl leading-relaxed text-slate-600">
                {{ content('accueil.section_services_intro') }}
            </p>
        </div>
    </section>

    {{-- Appel à l'action --}}
    <section class="mx-auto max-w-6xl px-4 py-16 text-center sm:px-6">
        <h2 class="text-2xl font-bold tracking-tight text-brand-900 sm:text-3xl">
            {{ content('accueil.cta_titre') }}
        </h2>

        <a href="{{ route('depot.create') }}"
           class="mt-8 inline-block rounded-md bg-brand-700 px-6 py-3 font-medium text-white transition hover:bg-brand-800">
            {{ content('accueil.cta_bouton', 'Commencer maintenant') }}
        </a>
    </section>
</x-public-layout>
