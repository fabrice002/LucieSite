@php
    $services = $services ?? collect();
@endphp

<x-public-layout
    :title="content('services.meta_titre', content('global.nav_services'))"
    :description="content('services.meta_description')"
>
    <x-page-header
        :titre="content('services.titre', 'Nos services')"
        :introduction="content('services.introduction')"
    />

    {{-- Blocs libres composés depuis le back-office. --}}
    <x-page-sections page="services" />

    @if ($services->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <article class="flex flex-col overflow-hidden rounded-xl border border-line bg-surface-raised transition hover:border-brand">
                        @if ($service->image_path)
                            <x-content-image
                                :chemin="$service->image_path"
                                :alt="$service->image_alt ?? $service->title"
                                :hauteur="450"
                                class="h-44 w-full object-cover"
                            />
                        @endif

                        <div class="flex flex-1 flex-col p-6">
                            @if ($service->highlight)
                                <span class="mb-3 self-start rounded-full bg-brand-soft px-3 py-1 text-xs font-medium text-brand-text">
                                    {{ $service->highlight }}
                                </span>
                            @endif

                            <h2 class="text-lg font-semibold text-ink-strong">
                                <a href="{{ route('services.show', $service) }}" class="hover:text-brand-text">
                                    {{ $service->title }}
                                </a>
                            </h2>

                            <p class="mt-2 flex-1 text-sm leading-relaxed text-ink-muted">{{ $service->summary }}</p>

                            <a href="{{ route('services.show', $service) }}"
                               class="mt-4 self-start text-sm font-medium text-brand-text hover:underline">
                                {{ content('services.lien_detail', 'En savoir plus') }} &rarr;
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <x-cta-band
        :titre="content('services.cta_titre')"
        :bouton="content('services.cta_bouton', 'Déposer mon dossier')"
        :url="route('depot.create')"
    />
</x-public-layout>
