<x-public-layout
    :title="content('temoignages.meta_titre', content('global.nav_temoignages'))"
    :description="content('temoignages.meta_description')"
>
    <x-page-header
        :titre="content('temoignages.titre', 'Ils nous ont fait confiance')"
        :introduction="content('temoignages.introduction')"
    />

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        @if ($temoignages->isEmpty())
            <p class="rounded-xl border border-line bg-surface-muted p-8 text-center text-ink-muted">
                {{ content('temoignages.aucun') }}
            </p>
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($temoignages as $temoignage)
                    <x-testimonial-card :temoignage="$temoignage" />
                @endforeach
            </div>
        @endif
    </section>

    <x-cta-band
        :titre="content('temoignages.cta_titre')"
        :bouton="content('temoignages.cta_bouton', 'Déposer mon dossier')"
    />
</x-public-layout>
