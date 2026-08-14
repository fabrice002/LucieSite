@php
    $categories = $categories ?? collect();
    $questions = $categories->flatMap->publishedFaqs;
@endphp

<x-public-layout
    :title="content('faq.meta_titre', content('global.nav_faq'))"
    :description="content('faq.meta_description')"
>
    <x-page-header
        :titre="content('faq.titre', 'Questions fréquentes')"
        :introduction="content('faq.introduction')"
    />

    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6" data-faq>
        @if ($questions->isNotEmpty())
            {{-- Filtre à la frappe. Sans JavaScript, toutes les questions
                 restent visibles : le champ est simplement inopérant. --}}
            <label for="faq-recherche" class="sr-only">{{ content('faq.recherche_label', 'Rechercher une question') }}</label>
            <input type="search"
                   id="faq-recherche"
                   data-faq-recherche
                   hidden
                   placeholder="{{ content('faq.recherche_placeholder', 'Rechercher une question…') }}"
                   class="mb-8 block w-full rounded-md border border-line bg-surface-raised px-4 py-3 text-base text-ink shadow-sm focus:border-brand focus:ring-2 focus:ring-brand-line focus:outline-none">

            <p data-faq-vide hidden class="rounded-xl border border-notice-line bg-notice p-6 text-notice-ink">
                {{ content('faq.recherche_vide', 'Aucune question ne correspond à votre recherche.') }}
            </p>
        @endif

        @forelse ($categories as $categorie)
            <section data-faq-categorie class="mb-10">
                <h2 class="mb-4 text-lg font-semibold text-ink-strong">{{ $categorie->name }}</h2>

                <div class="divide-y divide-line rounded-xl border border-line bg-surface-raised">
                    @foreach ($categorie->publishedFaqs as $faq)
                        {{-- <details> natif : accessible, et fonctionne sans JavaScript. --}}
                        <details id="{{ $faq->anchor() }}"
                                 data-faq-item
                                 data-faq-texte="{{ Str::lower($faq->question.' '.strip_tags($faq->answer)) }}"
                                 class="group px-6 py-5">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-ink-strong">
                                {{ $faq->question }}

                                <svg class="size-5 shrink-0 text-ink-muted transition group-open:rotate-180"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </summary>

                            <div class="prose-simple mt-3 leading-relaxed text-ink-muted">
                                {!! $faq->answer !!}
                            </div>

                            <a href="#{{ $faq->anchor() }}"
                               class="mt-3 inline-block text-xs text-ink-muted hover:text-brand-text"
                               aria-label="{{ content('faq.lien_direct', 'Lien direct vers cette question') }}">
                                {{ content('faq.lien_direct', 'Lien direct vers cette question') }}
                            </a>
                        </details>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="rounded-xl border border-line bg-surface-raised p-6 text-ink-muted">
                {{ content('faq.vide', 'Les questions fréquentes seront publiées prochainement.') }}
            </p>
        @endforelse
    </div>

    <x-cta-band
        :titre="content('faq.cta_titre')"
        :bouton="content('faq.cta_bouton', 'Nous contacter')"
        :url="route('contact')"
    />

    @if ($questions->isNotEmpty())
        {{-- Balisage FAQPage : permet l'affichage enrichi dans les moteurs. --}}
        @php
            $balisage = json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $questions->map(fn ($faq): array => [
                    '@type' => 'Question',
                    'name' => $faq->question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => trim(strip_tags($faq->answer)),
                    ],
                ])->values()->all(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        @endphp

        <script type="application/ld+json">{!! $balisage !!}</script>

        @push('scripts')
            @vite('resources/js/faq.js')
        @endpush
    @endif
</x-public-layout>
