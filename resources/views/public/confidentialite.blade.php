<x-public-layout :title="content('confidentialite.meta_titre', content('confidentialite.titre'))">
    <x-page-header :titre="content('confidentialite.titre', 'Politique de confidentialité')" />

    <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <div class="prose-simple leading-relaxed text-ink-muted">
            {!! content('confidentialite.introduction_html') !!}
        </div>

        <div class="mt-10 space-y-10">
            @foreach (['donnees', 'finalite', 'conservation', 'securite', 'droits', 'contact'] as $section)
                <article>
                    <h2 class="text-xl font-semibold text-ink-strong">
                        {{ content("confidentialite.{$section}_titre") }}
                    </h2>

                    {{-- Contenu mis en forme depuis le back-office (RichEditor). --}}
                    <div class="prose-simple mt-3 leading-relaxed text-ink-muted">
                        {!! content("confidentialite.{$section}_html") !!}
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</x-public-layout>
