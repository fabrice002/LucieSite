{{-- Marque du back-office. Le logo se change dans « Apparence », à défaut dans
     config/brand.php.

     Les classes Tailwind employées ici sont générées par le thème du panel
     (resources/css/filament/admin/theme.css), qui déclare ce dossier comme
     source. Sans ce thème, Filament sert sa feuille précompilée et aucune de
     ces classes n'existerait : le monogramme s'afficherait sans dimension.

     Les couleurs viennent des mêmes clés que le favicon : la marque reste
     identique dans les pages, dans le back-office et dans l'onglet. --}}
<div class="flex items-center gap-2.5">
    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg"
          style="background-color: {{ config('brand.icone_fond', '#1e40af') }}; color: {{ config('brand.icone_trait', '#ffffff') }};">
        <x-app-logo-icon class="size-4 object-contain" />
    </span>

    <span class="text-lg font-semibold whitespace-nowrap text-gray-950 dark:text-white">
        {{ config('app.name', 'LN Immigration') }}
    </span>
</div>
