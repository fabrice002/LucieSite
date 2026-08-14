@props([
    'chemin' => null,
    'alt' => '',
    'largeur' => 1600,
    'hauteur' => 900,
    'classe' => '',
])

{{-- Image de contenu.
     Les visiteurs sont en 3G : chargement différé et dimensions déclarées, pour
     que la page ne saute pas pendant le chargement. --}}
@if (filled($chemin))
    <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($chemin) }}"
         alt="{{ $alt }}"
         width="{{ $largeur }}"
         height="{{ $hauteur }}"
         loading="lazy"
         decoding="async"
         {{ $attributes->merge(['class' => $classe]) }}>
@endif
