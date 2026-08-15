@props([
    'bloc' => 'accueil',
    'prefixe' => 'etape',
])

{{--
    « Notre processus », en étapes numérotées.

    Le candidat veut savoir ce qui va lui arriver avant de s'engager : c'est le
    bloc qui transforme une page vitrine en engagement lisible.

    Le nombre d'étapes n'est pas codé — il découle des clés présentes dans le
    bloc de textes. En ajouter une revient à ajouter une paire de clés au
    seeder, sans toucher à cette vue.
--}}
@php
    $etapes = [];

    foreach (app(App\Support\SiteContentRepository::class)->block($bloc) as $cle => $valeur) {
        if (! preg_match('/^'.preg_quote($prefixe, '/').'_(\d+)_titre$/', $cle, $trouve)) {
            continue;
        }

        $rang = (int) $trouve[1];
        $titre = content_filled("{$bloc}.{$prefixe}_{$rang}_titre");

        if ($titre !== null) {
            $etapes[$rang] = [
                'titre' => $titre,
                'texte' => content_filled("{$bloc}.{$prefixe}_{$rang}_texte"),
            ];
        }
    }

    ksort($etapes);
@endphp

@if ($etapes !== [])
    {{-- Classes écrites en toutes lettres : une classe construite à la volée
         (lg:grid-cols-{{ $n }}) n'existerait jamais dans le CSS compilé, faute
         d'être visible du compilateur au build. --}}
    <ol @class([
        'mt-10 grid gap-6 sm:grid-cols-2',
        'lg:grid-cols-3' => count($etapes) === 3,
        'lg:grid-cols-4' => count($etapes) !== 3,
    ])>
        @foreach ($etapes as $numero => $etape)
            <li class="relative rounded-xl border border-line bg-surface-raised p-6">
                <span aria-hidden="true"
                      class="flex size-10 items-center justify-center rounded-full bg-brand text-base font-semibold text-brand-contrast">
                    {{ $loop->iteration }}
                </span>

                <h3 class="mt-4 font-semibold text-ink-strong">
                    <span class="sr-only">{{ content('global.etape_prefixe', 'Étape') }} {{ $loop->iteration }} — </span>
                    {{ $etape['titre'] }}
                </h3>

                @if ($etape['texte'] !== null)
                    <p class="mt-2 text-sm leading-relaxed text-ink-muted">{{ $etape['texte'] }}</p>
                @endif
            </li>
        @endforeach
    </ol>
@endif
