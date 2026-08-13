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
                    <figure class="flex flex-col rounded-xl border border-line bg-surface-raised p-6">
                        <blockquote class="flex-1 leading-relaxed text-ink">
                            {{ $temoignage->content }}
                        </blockquote>

                        <figcaption class="mt-6 flex items-center gap-3 border-t border-line pt-4">
                            @if ($temoignage->photo_path)
                                <img src="{{ Storage::disk('public')->url($temoignage->photo_path) }}"
                                     alt=""
                                     loading="lazy"
                                     class="size-11 rounded-full object-cover">
                            @else
                                <span class="flex size-11 items-center justify-center rounded-full bg-brand-soft font-semibold text-brand-text">
                                    {{ Str::of($temoignage->author_name)->substr(0, 1)->upper() }}
                                </span>
                            @endif

                            <span>
                                <span class="block font-medium text-ink-strong">{{ $temoignage->author_name }}</span>
                                @if ($temoignage->author_country)
                                    <span class="block text-sm text-ink-muted">{{ $temoignage->author_country }}</span>
                                @endif
                            </span>
                        </figcaption>

                        @if ($temoignage->video_url)
                            <a href="{{ $temoignage->video_url }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="mt-4 text-sm font-medium text-brand-text hover:underline">
                                Voir la vidéo
                            </a>
                        @endif
                    </figure>
                @endforeach
            </div>
        @endif
    </section>

    <x-cta-band
        :titre="content('temoignages.cta_titre')"
        :bouton="content('temoignages.cta_bouton', 'Déposer mon dossier')"
    />
</x-public-layout>
