<x-public-layout
    :title="content('faq.meta_titre', content('global.nav_faq'))"
    :description="content('faq.meta_description')"
>
    <x-page-header
        :titre="content('faq.titre', 'Questions fréquentes')"
        :introduction="content('faq.introduction')"
    />

    <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <div class="divide-y divide-line rounded-xl border border-line bg-surface-raised">
            @foreach (range(1, 6) as $n)
                @php
                    $question = content("faq.question_{$n}");
                    $reponse = content("faq.reponse_{$n}");
                @endphp

                @if (filled($question))
                    {{-- <details> natif : accessible et sans JavaScript. --}}
                    <details class="group px-6 py-5" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-ink-strong">
                            {{ $question }}
                            <svg class="size-5 shrink-0 text-ink-muted transition group-open:rotate-180"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </summary>

                        <p class="mt-3 leading-relaxed text-ink-muted">{{ $reponse }}</p>
                    </details>
                @endif
            @endforeach
        </div>
    </section>

    <x-cta-band
        :titre="content('faq.cta_titre')"
        :bouton="content('faq.cta_bouton', 'Nous contacter')"
        :url="route('contact')"
    />
</x-public-layout>
