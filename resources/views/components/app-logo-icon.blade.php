{{--
    Marque du site — source unique.

    Le logo se règle depuis le back-office, section « Apparence ». À défaut,
    config/brand.php (clé « logo ») reste honoré, et sans rien du tout le
    monogramme est tracé. Voir la section « Changer le logo » du README.

    Le logo sombre est facultatif : sans lui, le logo clair sert dans les deux
    thèmes. Les classes marque-clair / marque-sombre sont définies dans les
    feuilles du site, pas générées par Tailwind.
--}}
@php
    $theme = app(App\Support\ThemePublic::class);
    $logoClair = $theme->urlLogo('clair');
    $logoSombre = $theme->urlLogo('sombre');
    $nom = config('app.name', 'LN Immigration');
    $attributs = $attributes ?? new Illuminate\View\ComponentAttributeBag;
@endphp

@if (filled($logoClair) && filled($logoSombre))
    <img src="{{ $logoClair }}" alt="{{ $nom }}"
         {{ $attributs->merge(['class' => 'marque-clair object-contain']) }}>
    <img src="{{ $logoSombre }}" alt="" aria-hidden="true"
         {{ $attributs->merge(['class' => 'marque-sombre object-contain']) }}>
@elseif (filled($logoClair))
    <img src="{{ $logoClair }}" alt="{{ $nom }}"
         {{ $attributs->merge(['class' => 'object-contain']) }}>
@else
    {{-- Monogramme de repli, tracé depuis config/brand.php.
         Il utilise currentColor : il suit donc le thème clair ou sombre. --}}
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"
         role="img" aria-label="{{ $nom }}" {{ $attributs }}>
        @foreach (config('brand.monogramme', []) as $lettre)
            <polygon fill="currentColor"
                     points="{{ collect($lettre)->map(fn (array $point): string => implode(',', $point))->implode(' ') }}" />
        @endforeach
    </svg>
@endif
