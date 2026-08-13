@props([
    'title' => null,
    'description' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? content('global.nom_site', 'LN Immigration') }}</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:title" content="{{ $title ?? content('global.nom_site', 'LN Immigration') }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    @fonts
    @vite('resources/css/public.css')
</head>
<body class="flex min-h-screen flex-col bg-white text-slate-800 antialiased">

    <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-brand-700 focus:px-4 focus:py-2 focus:text-white">
        Aller au contenu
    </a>

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight text-brand-800">
                {{ content('global.nom_site', 'LN Immigration') }}
            </a>

            <nav class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm" aria-label="Navigation principale">
                <a href="{{ route('home') }}"
                   @class([
                       'py-1 hover:text-brand-700',
                       'font-semibold text-brand-700' => request()->routeIs('home'),
                   ])>
                    {{ content('global.nav_accueil', 'Accueil') }}
                </a>

                <a href="{{ route('suivi.index') }}"
                   @class([
                       'py-1 hover:text-brand-700',
                       'font-semibold text-brand-700' => request()->routeIs('suivi.*'),
                   ])>
                    {{ content('global.nav_suivre', 'Suivre mon dossier') }}
                </a>

                <a href="{{ route('depot.create') }}"
                   class="rounded-md bg-brand-700 px-4 py-2 font-medium text-white transition hover:bg-brand-800">
                    {{ content('global.nav_deposer', 'Déposer mon dossier') }}
                </a>
            </nav>
        </div>
    </header>

    <main id="contenu" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-slate-200 bg-slate-50">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-3">
            <div>
                <p class="font-semibold text-brand-800">{{ content('global.nom_site', 'LN Immigration') }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ content('global.baseline') }}</p>
            </div>

            <div class="text-sm text-slate-600">
                <p>{{ content('global.footer_adresse') }}</p>
                <p class="mt-1">{{ content('global.footer_telephone') }}</p>
                <p class="mt-1">{{ content('global.footer_email') }}</p>
            </div>

            <div class="flex flex-col gap-2 text-sm">
                <a href="{{ route('depot.create') }}" class="text-slate-600 hover:text-brand-700">
                    {{ content('global.nav_deposer', 'Déposer mon dossier') }}
                </a>
                <a href="{{ route('suivi.index') }}" class="text-slate-600 hover:text-brand-700">
                    {{ content('global.nav_suivre', 'Suivre mon dossier') }}
                </a>
            </div>
        </div>

        <div class="border-t border-slate-200 px-4 py-4 text-center text-xs text-slate-500 sm:px-6">
            &copy; {{ now()->year }} {{ content('global.nom_site', 'LN Immigration') }}.
            {{ content('global.footer_copyright', 'Tous droits réservés.') }}
        </div>
    </footer>

</body>
</html>
