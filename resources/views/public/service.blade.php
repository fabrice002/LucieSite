<x-public-layout
    :title="$service->meta_title ?: $service->title.' — '.content('global.nom_site')"
    :description="$service->meta_description ?: $service->summary"
>
    <x-page-header :titre="$service->title" :introduction="$service->summary" />

    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        @if ($service->image_path)
            <x-content-image
                :chemin="$service->image_path"
                :alt="$service->image_alt ?? $service->title"
                class="mb-10 w-full rounded-xl border border-line object-cover"
            />
        @endif

        @if (filled($service->body))
            <div class="prose-simple leading-relaxed text-ink-muted">
                {!! $service->body !!}
            </div>
        @endif

        <nav class="mt-12 border-t border-line pt-6">
            <a href="{{ route('services') }}" class="text-sm font-medium text-brand-text hover:underline">
                &larr; {{ content('services.retour', 'Tous nos services') }}
            </a>
        </nav>
    </article>

    <x-cta-band
        :titre="content('services.cta_titre')"
        :bouton="content('services.cta_bouton', 'Déposer mon dossier')"
        :url="route('depot.create')"
    />
</x-public-layout>
