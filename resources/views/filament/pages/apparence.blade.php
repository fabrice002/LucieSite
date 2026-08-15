<x-filament-panels::page>
    <form wire:submit="enregistrer">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Enregistrer
            </x-filament::button>
        </div>
    </form>

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Où ces réglages s'appliquent-ils ?</x-slot>

        <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
            <p>
                Les couleurs et la police valent pour le <strong>site public</strong> : pages
                vitrines, formulaire de dépôt et page de suivi. Le back-office garde son
                apparence propre, pour rester lisible quel que soit le réglage choisi.
            </p>
            <p>
                Une couleur enregistrée est appliquée <strong>immédiatement</strong> : il n'y a
                rien à recompiler ni à vider. Les nuances de survol, de fond et de filet sont
                calculées à partir de la couleur principale.
            </p>
            <p>
                L'icône d'onglet est un fichier que les navigateurs conservent très longtemps.
                Après « Régénérer les icônes », videz le cache du vôtre pour la voir changer.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
