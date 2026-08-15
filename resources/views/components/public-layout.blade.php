@props([
    'title' => null,
    'description' => null,
])

@php
    $siteName = content('global.nom_site', 'LN Immigration');
    $pageTitle = $title ?: $siteName;

    // Apparence réglée depuis le back-office : couleurs, police, thème sombre.
    $theme = app(App\Support\ThemePublic::class);

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

    @if ($favicon = $theme->urlFavicon())
        <link rel="icon" href="{{ $favicon }}">
    @else
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @endif

    @if ($theme->themeSombreActif())
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
    @endif

    {{-- Une seule famille est demandée, celle qui est réglée : les autres ne
         sont ni préchargées ni téléchargées. La 3G ne pardonne pas. --}}
    @fonts([$theme->police()])
    @vite('resources/css/public.css')

    {{-- Après la feuille, pour surcharger les jetons qu'elle déclare. --}}
    <style>{!! $theme->css() !!}</style>
</head>
<body class="flex min-h-screen flex-col bg-surface text-ink antialiased">

    <a href="#contenu"
       class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-brand focus:px-4 focus:py-2 focus:text-white">
        Aller au contenu
    </a>

    <header class="sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="flex size-9 items-center justify-center rounded-lg bg-brand text-brand-contrast">
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
                @if ($theme->themeSombreActif())
                    <x-theme-toggle />
                @endif

                <a href="{{ route('suivi.index') }}"
                   class="hidden rounded-md border border-line px-3 py-2 text-sm font-medium text-ink transition hover:border-brand hover:text-brand-text sm:inline-block">
                    {{ content('global.nav_suivre', 'Suivre mon dossier') }}
                </a>

                {{-- L'appel à l'action dominant, présent dès le premier écran
                     et sur toutes les pages. --}}
                <a href="{{ route('depot.create') }}"
                   class="rounded-md bg-brand px-3 py-2 text-sm font-medium text-brand-contrast transition hover:bg-brand-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
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

                        <a href="{{ route('depot.create') }}"
                           class="mt-1 block rounded-md bg-brand px-3 py-2 text-center text-sm font-medium text-brand-contrast">
                            {{ content('global.nav_deposer', 'Déposer mon dossier') }}
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
                    <span class="flex size-8 items-center justify-center rounded-lg bg-brand text-brand-contrast">
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

            {{-- Coordonnées complètes : dans ce secteur, une adresse physique
                 vérifiable est l'un des principaux signaux de légitimité. Rien
                 n'est affiché tant que la cliente n'a pas renseigné la valeur —
                 un « [À COMPLÉTER] » en ligne serait pire que l'absence. --}}
            @php
                $adresse = content_filled('global.footer_adresse');
                $telephone = content_filled('global.footer_telephone');
                $whatsapp = content_filled('global.footer_whatsapp');
                $courriel = content_filled('global.footer_email');
                $horaires = content_filled('global.footer_horaires');
                $lienDiscret = 'rounded transition hover:text-brand-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
            @endphp

            <div>
                <p class="text-sm font-semibold text-ink-strong">
                    {{ content('global.footer_titre_contact', 'Nous joindre') }}
                </p>

                <address class="mt-3 space-y-2 text-sm text-ink-muted not-italic">
                    @if ($adresse)
                        <p>{{ $adresse }}</p>
                    @endif

                    @if ($telephone)
                        <p>
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $telephone) }}" class="{{ $lienDiscret }}">
                                {{ $telephone }}
                            </a>
                        </p>
                    @endif

                    @if ($whatsapp)
                        <p>
                            <a href="https://wa.me/{{ preg_replace('/\D/', '', $whatsapp) }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="{{ $lienDiscret }}">
                                WhatsApp {{ $whatsapp }}
                            </a>
                        </p>
                    @endif

                    @if ($courriel)
                        <p>
                            <a href="mailto:{{ $courriel }}" class="{{ $lienDiscret }}">{{ $courriel }}</a>
                        </p>
                    @endif

                    @if ($horaires)
                        <p class="pt-1">{{ $horaires }}</p>
                    @endif
                </address>

                {{-- À quel titre le cabinet intervient. Reste masqué tant que ce
                     n'est pas renseigné : aucun agrément n'est supposé ici. --}}
                @if ($statut = content_filled('global.statut_professionnel'))
                    <p class="mt-4 border-t border-line pt-3 text-xs leading-relaxed text-ink-muted">
                        {{ $statut }}
                    </p>
                @endif
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
