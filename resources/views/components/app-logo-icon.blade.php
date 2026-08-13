{{--
    Marque du site — source unique.

    Le logo se change dans config/brand.php (clé « logo »), et se répercute
    partout : site public, page de connexion, back-office et onglet du
    navigateur. Voir la section « Changer le logo » du README.
--}}
@php
    $logo = config('brand.logo');
    $attributs = $attributes ?? new Illuminate\View\ComponentAttributeBag;
@endphp

@if (filled($logo))
    <img src="{{ asset($logo) }}"
         alt="{{ config('app.name', 'LN Immigration') }}"
         {{ $attributs->merge(['class' => 'object-contain']) }}>
@else
    {{-- Monogramme de repli, tracé depuis config/brand.php.
         Il utilise currentColor : il suit donc le thème clair ou sombre. --}}
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"
         role="img" aria-label="{{ config('app.name', 'LN Immigration') }}" {{ $attributs }}>
        @foreach (config('brand.monogramme', []) as $lettre)
            <polygon fill="currentColor"
                     points="{{ collect($lettre)->map(fn (array $point): string => implode(',', $point))->implode(' ') }}" />
        @endforeach
    </svg>
@endif
