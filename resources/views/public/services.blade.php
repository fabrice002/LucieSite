<x-public-layout
    :title="content('services.meta_titre', content('global.nav_services'))"
    :description="content('services.meta_description')"
>
    <x-page-header
        :titre="content('services.titre', 'Nos services')"
        :introduction="content('services.introduction')"
    />

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach (range(1, 6) as $n)
                <article class="rounded-xl border border-line bg-surface-raised p-6 transition hover:border-brand">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-brand-soft font-semibold text-brand-text">
                        {{ $n }}
                    </span>

                    <h2 class="mt-4 text-lg font-semibold text-ink-strong">
                        {{ content("services.service_{$n}_titre") }}
                    </h2>

                    <p class="mt-2 text-sm leading-relaxed text-ink-muted">
                        {{ content("services.service_{$n}_texte") }}
                    </p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="border-y border-line bg-surface-muted">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-ink-strong">
                {{ content('services.tarifs_titre', 'Nos tarifs') }}
            </h2>

            <p class="mt-4 max-w-3xl leading-relaxed text-ink-muted">
                {{ content('services.tarifs_texte') }}
            </p>
        </div>
    </section>

    <x-cta-band
        :titre="content('services.cta_titre')"
        :bouton="content('services.cta_bouton', 'Déposer mon dossier')"
    />
</x-public-layout>
