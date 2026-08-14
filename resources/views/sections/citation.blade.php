<section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
    <figure class="rounded-2xl border border-brand-line bg-brand-soft px-6 py-10 text-center sm:px-12">
        <blockquote class="text-xl leading-relaxed text-ink-strong italic">
            « {{ $section->valeur('texte') }} »
        </blockquote>

        @if ($auteur = $section->valeur('auteur'))
            <figcaption class="mt-6 text-sm text-ink-muted">
                <span class="font-medium text-ink-strong">{{ $auteur }}</span>

                @if ($fonction = $section->valeur('fonction'))
                    <span class="block">{{ $fonction }}</span>
                @endif
            </figcaption>
        @endif
    </figure>
</section>
