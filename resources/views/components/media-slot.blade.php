@props([
    'chemin' => null,
    'alt' => '',
    'largeur' => 1200,
    'hauteur' => 800,
    'ratio' => 'aspect-[3/2]',
])

{{--
    Emplacement d'image.

    Tant que la cliente n'a pas fourni sa photo, on affiche un aplat de la
    charte — jamais une image de banque. Les analyses du secteur convergent :
    un visuel générique de bureau souriant est le premier signal de défiance
    pour un candidat qui cherche à distinguer un vrai cabinet d'une arnaque.
--}}
@if (filled($chemin))
    <x-content-image
        :chemin="$chemin"
        :alt="$alt"
        :largeur="$largeur"
        :hauteur="$hauteur"
        {{ $attributes->merge(['class' => $ratio.' w-full rounded-xl object-cover']) }}
    />
@else
    <div aria-hidden="true"
         {{ $attributes->merge(['class' => $ratio.' w-full overflow-hidden rounded-xl border border-brand-line bg-brand-soft']) }}>
        {{-- Motif discret tracé en CSS : rien à télécharger. --}}
        <div class="size-full bg-[radial-gradient(circle_at_30%_25%,var(--color-brand-line)_0,transparent_45%),radial-gradient(circle_at_75%_70%,var(--color-brand-line)_0,transparent_40%)] opacity-70"></div>
    </div>
@endif
