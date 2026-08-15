@props(['temoignage'])

{{--
    Carte de témoignage.

    Prénom, pays et programme obtenu sont affichés ensemble : un témoignage
    anonyme et sans contexte ne rassure personne, et c'est justement la forme
    qu'empruntent les faux avis. La photo n'apparaît que si la personne en a
    fourni une.
--}}
<figure class="flex h-full flex-col rounded-xl border border-line bg-surface-raised p-6">
    <blockquote class="flex-1 leading-relaxed text-ink">
        {{ $temoignage->content }}
    </blockquote>

    <figcaption class="mt-6 flex items-start gap-3 border-t border-line pt-4">
        @if ($temoignage->photo_path)
            <img src="{{ Storage::disk('public')->url($temoignage->photo_path) }}"
                 alt=""
                 loading="lazy"
                 decoding="async"
                 width="88"
                 height="88"
                 class="size-11 shrink-0 rounded-full object-cover">
        @else
            <span aria-hidden="true"
                  class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-soft font-semibold text-brand-text">
                {{ Str::of($temoignage->author_name)->trim()->substr(0, 1)->upper() }}
            </span>
        @endif

        <span class="min-w-0">
            <span class="block font-medium text-ink-strong">{{ $temoignage->author_name }}</span>

            @if ($temoignage->author_country)
                <span class="block text-sm text-ink-muted">{{ $temoignage->author_country }}</span>
            @endif

            @if ($temoignage->author_programme)
                <span class="mt-1.5 inline-block rounded-full bg-brand-soft px-2.5 py-0.5 text-xs font-medium text-brand-text">
                    {{ $temoignage->author_programme }}
                </span>
            @endif
        </span>
    </figcaption>

    @if ($temoignage->video_url)
        <a href="{{ $temoignage->video_url }}"
           target="_blank"
           rel="noopener noreferrer"
           class="mt-4 inline-flex items-center gap-1.5 rounded text-sm font-medium text-brand-text hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M8 5.14v13.72L19 12 8 5.14Z" />
            </svg>
            {{ content('temoignages.lien_video', 'Voir la vidéo') }}
            <span class="sr-only">({{ content('temoignages.video_nouvel_onglet', 'ouvre un nouvel onglet') }})</span>
        </a>
    @endif
</figure>
