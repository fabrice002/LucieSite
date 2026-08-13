<x-public-layout :title="content('mentions_legales.meta_titre', content('mentions_legales.titre'))">
    <x-page-header :titre="content('mentions_legales.titre', 'Mentions légales')" />

    <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <div class="space-y-10">
            @foreach (['editeur', 'hebergeur', 'propriete', 'responsabilite'] as $section)
                <article>
                    <h2 class="text-xl font-semibold text-ink-strong">
                        {{ content("mentions_legales.{$section}_titre") }}
                    </h2>

                    {{-- Contenu mis en forme depuis le back-office (RichEditor). --}}
                    <div class="prose-simple mt-3 leading-relaxed text-ink-muted">
                        {!! content("mentions_legales.{$section}_html") !!}
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</x-public-layout>
