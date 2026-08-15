{{-- Marque du back-office. Le logo se change dans config/brand.php.

     Les dimensions sont posées en styles en ligne, volontairement. Le panel
     Filament sert sa propre CSS précompilée, qui ne contient pas les
     utilitaires du site (size-*, bg-brand, shrink-0…) : une classe Tailwind
     écrite ici ne s'appliquerait nulle part. Le monogramme s'afficherait alors
     sans dimension et recouvrirait la navigation.

     Les couleurs viennent des mêmes clés que le favicon : la marque reste
     identique dans les pages, dans le back-office et dans l'onglet. --}}
<div style="display: flex; align-items: center; gap: 0.625rem;">
    <span style="display: inline-flex; flex: none; align-items: center; justify-content: center;
                 width: 2rem; height: 2rem; border-radius: 0.5rem;
                 background-color: {{ config('brand.icone_fond', '#1e40af') }};
                 color: {{ config('brand.icone_trait', '#ffffff') }};">
        <x-app-logo-icon style="display: block; width: 1rem; height: 1rem; object-fit: contain;" />
    </span>

    {{-- color: inherit — le nom suit le thème clair ou sombre du panel. --}}
    <span style="font-size: 1.125rem; line-height: 1.75rem; font-weight: 600; color: inherit; white-space: nowrap;">
        {{ config('app.name', 'LN Immigration') }}
    </span>
</div>
