@props([
    'title' => null,
    'description' => null,
])

@php
    $siteName = content('global.nom_site', 'LN Immigration');
    $pageTitle = $title ?: $siteName;

    // Une seule source de vérité pour la navigation : en-tête, menu mobile et
    // pied de page lisent la même liste.
    $navigation = [
        ['route' => 'home', 'cle' => 'global.nav_accueil', 'defaut' => 'Accueil'],
        ['route' => 'services', 'cle' => 'global.nav_services', 'defaut' => 'Services'],
        ['route' => 'a-propos', 'cle' => 'global.nav_a_propos', 'defaut' => 'À propos'],
        ['route' => 'temoignages', 'cle' => 'global.nav_temoignages', 'defaut' => 'Témoignages'],
        ['route' => 'faq', 'cle' => 'global.nav_faq', 'defaut' => 'FAQ'],
        ['route' => 'contact', 'cle' => 'global.nav_contact', 'defaut' => 'Contact'],
    ];

    $lienNav = 'rounded-md px-2 py-1.5 text-sm text-ink-muted transition hover:bg-surface-muted hover:text-brand-text';
    $lienNavActif = 'rounded-md bg-brand-soft px-2 py-1.5 text-sm font-semibold text-brand-text';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    {{-- Le thème est appliqué avant le premier rendu pour éviter tout
         clignotement. La clé est celle du back-office : le choix de
         l'utilisateur le suit d'un bout à l'autre du site. --}}
    <script>
        (function () {
            try {
                var choix = localStorage.getItem('flux.appearance');
                var sombre = choix === 'dark'
                    || (choix !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', sombre);
            } catch (e) {}
        })();
    </script>

    @fonts
    @vite('resources/css/public.css')
</head>
<body class="flex min-h-screen flex-col bg-surface text-ink antialiased">

    <a href="#contenu"
       class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-brand focus:px-4 focus:py-2 focus:text-white">
        Aller au contenu
    </a>

    <header class="sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="flex size-9 items-center justify-center rounded-lg bg-brand text-white">
                    <x-app-logo-icon class="size-5" />
                </span>
                <span class="text-base font-semibold tracking-tight text-ink-strong">{{ $siteName }}</span>
            </a>

            {{-- Navigation bureau --}}
            <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigation principale">
                @foreach ($navigation as $lien)
                    <a href="{{ route($lien['route']) }}"
                       @class([$lienNav => ! request()->routeIs($lien['route']), $lienNavActif => request()->routeIs($lien['route'])])
                       @if (request()->routeIs($lien['route'])) aria-current="page" @endif>
                        {{ content($lien['cle'], $lien['defaut']) }}
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <x-theme-toggle />

                <a href="{{ route('suivi.index') }}"
                   class="hidden rounded-md border border-line px-3 py-2 text-sm font-medium text-ink transition hover:border-brand hover:text-brand-text sm:inline-block">
                    {{ content('global.nav_suivre', 'Suivre mon dossier') }}
                </a>

                <a href="{{ route('depot.create') }}"
                   class="rounded-md bg-brand px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-hover">
                    {{ content('global.nav_deposer', 'Déposer mon dossier') }}
                </a>

                {{-- Menu mobile : <details> natif, aucun JavaScript requis. --}}
                <details class="relative lg:hidden">
                    <summary class="flex size-9 cursor-pointer list-none items-center justify-center rounded-md border border-line text-ink"
                             aria-label="Ouvrir le menu">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </summary>

                    <nav class="absolute right-0 z-50 mt-2 w-56 rounded-lg border border-line bg-surface-raised p-2 shadow-lg"
                         aria-label="Navigation mobile">
                        @foreach ($navigation as $lien)
                            <a href="{{ route($lien['route']) }}"
                               class="block rounded-md px-3 py-2 text-sm text-ink transition hover:bg-surface-muted hover:text-brand-text">
                                {{ content($lien['cle'], $lien['defaut']) }}
                            </a>
                        @endforeach

                        <span class="my-2 block border-t border-line"></span>

                        <a href="{{ route('suivi.index') }}"
                           class="block rounded-md px-3 py-2 text-sm text-ink transition hover:bg-surface-muted hover:text-brand-text">
                            {{ content('global.nav_suivre', 'Suivre mon dossier') }}
                        </a>
                    </nav>
                </details>
            </div>
        </div>
    </header>

    <main id="contenu" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="mt-20 border-t border-line bg-surface-muted">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-4">
            <div class="md:col-span-2">
                <div class="flex items-center gap-2.5">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-brand text-white">
                        <x-app-logo-icon class="size-4" />
                    </span>
                    <span class="font-semibold text-ink-strong">{{ $siteName }}</span>
                </div>
                <p class="mt-3 max-w-sm text-sm leading-relaxed text-ink-muted">
                    {{ content('global.baseline') }}
                </p>
            </div>

            <div>
                <p class="text-sm font-semibold text-ink-strong">
                    {{ content('global.footer_titre_navigation', 'Le cabinet') }}
                </p>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($navigation as $lien)
                        <li>
                            <a href="{{ route($lien['route']) }}" class="text-ink-muted transition hover:text-brand-text">
                                {{ content($lien['cle'], $lien['defaut']) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <p class="text-sm font-semibold text-ink-strong">
                    {{ content('global.footer_titre_contact', 'Nous joindre') }}
                </p>
                <ul class="mt-3 space-y-2 text-sm text-ink-muted">
                    <li>{{ content('global.footer_adresse') }}</li>
                    <li>{{ content('global.footer_telephone') }}</li>
                    <li>{{ content('global.footer_email') }}</li>
                </ul>
            </div>
        </div>

        <div class="border-t border-line">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-ink-muted sm:flex-row sm:px-6">
                <p>&copy; {{ now()->year }} {{ $siteName }}. {{ content('global.footer_copyright', 'Tous droits réservés.') }}</p>

                <div class="flex gap-4">
                    <a href="{{ route('mentions-legales') }}" class="transition hover:text-brand-text">
                        {{ content('global.footer_mentions', 'Mentions légales') }}
                    </a>
                    <a href="{{ route('confidentialite') }}" class="transition hover:text-brand-text">
                        {{ content('global.footer_confidentialite', 'Politique de confidentialité') }}
                    </a>
                </div>
            </div>
        </div>
    </footer>

    {{-- Chaque page ne charge que le JavaScript dont elle a besoin. --}}
    @stack('scripts')

</body>
</html>
