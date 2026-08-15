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
                    <x-service-card :service="$service" />
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
