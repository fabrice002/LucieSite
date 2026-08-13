{{-- Marque du back-office. Elle utilise le même composant que le site public
     et la page de connexion : le logo se change dans config/brand.php. --}}
<div class="flex items-center gap-2.5">
    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-white">
        <x-app-logo-icon class="size-4" />
    </span>

    <span class="text-lg font-semibold text-gray-950 dark:text-white">
        {{ config('app.name', 'LN Immigration') }}
    </span>
</div>
