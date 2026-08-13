<x-public-layout
    :title="content('a_propos.meta_titre', content('global.nav_a_propos'))"
    :description="content('a_propos.meta_description')"
>
    <x-page-header
        :titre="content('a_propos.titre', 'À propos du cabinet')"
        :introduction="content('a_propos.introduction')"
    />

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="grid gap-10 md:grid-cols-2">
            <article>
                <h2 class="text-2xl font-bold tracking-tight text-ink-strong">
                    {{ content('a_propos.histoire_titre', 'Notre histoire') }}
                </h2>
                <p class="mt-4 leading-relaxed text-ink-muted">
                    {{ content('a_propos.histoire_texte') }}
                </p>
            </article>

            <article>
                <h2 class="text-2xl font-bold tracking-tight text-ink-strong">
                    {{ content('a_propos.mission_titre', 'Notre mission') }}
                </h2>
                <p class="mt-4 leading-relaxed text-ink-muted">
                    {{ content('a_propos.mission_texte') }}
                </p>
            </article>
        </div>
    </section>

    <section class="border-y border-line bg-surface-muted">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <div class="grid gap-6 md:grid-cols-3">
                @foreach (range(1, 3) as $n)
                    <article class="rounded-xl border border-line bg-surface-raised p-6">
                        <h3 class="text-lg font-semibold text-ink-strong">
                            {{ content("a_propos.valeur_{$n}_titre") }}
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-muted">
                            {{ content("a_propos.valeur_{$n}_texte") }}
                        </p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-2xl font-bold tracking-tight text-ink-strong">
            {{ content('a_propos.equipe_titre', "L'équipe") }}
        </h2>
        <p class="mt-4 max-w-3xl leading-relaxed text-ink-muted">
            {{ content('a_propos.equipe_texte') }}
        </p>
    </section>

    <x-cta-band
        :titre="content('a_propos.cta_titre')"
        :bouton="content('a_propos.cta_bouton', 'Déposer mon dossier')"
    />
</x-public-layout>
