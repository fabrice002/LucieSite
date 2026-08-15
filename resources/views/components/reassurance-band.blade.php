@props(['bloc' => 'reassurance'])

{{--
    Bande de réassurance, sous le hero.

    Elle ne s'affiche que si la cliente a renseigné au moins un couple
    valeur / libellé. Les emplacements sont livrés vides : ce secteur attire la
    fraude, et un chiffre inventé — « 98 % de réussite » — dessert le cabinet
    autant que les candidats. Aucun plafond : autant d'entrées que voulu.
--}}
@php
    $elements = [];

    foreach (app(App\Support\SiteContentRepository::class)->block($bloc) as $cle => $valeur) {
        if (! preg_match('/^element_(\d+)_valeur$/', $cle, $trouve)) {
            continue;
        }

        $rang = $trouve[1];
        $chiffre = content_filled("{$bloc}.element_{$rang}_valeur");
        $libelle = content_filled("{$bloc}.element_{$rang}_libelle");

        if ($chiffre !== null && $libelle !== null) {
            $elements[(int) $rang] = ['valeur' => $chiffre, 'libelle' => $libelle];
        }
    }

    ksort($elements);
@endphp

@if ($elements !== [])
    <section class="border-b border-line bg-surface-muted" aria-label="{{ content('reassurance.titre', 'Le cabinet en bref') }}">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <dl class="grid grid-cols-2 gap-6 sm:gap-8 md:grid-cols-4">
                @foreach ($elements as $element)
                    <div class="text-center">
                        <dt class="sr-only">{{ $element['libelle'] }}</dt>
                        <dd>
                            <span class="block text-2xl font-bold tracking-tight text-brand-text sm:text-3xl">
                                {{ $element['valeur'] }}
                            </span>
                            <span class="mt-1 block text-sm leading-snug text-ink-muted">
                                {{ $element['libelle'] }}
                            </span>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>
@endif
