<x-filament-panels::page>
    {{ $this->table }}

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Pourquoi ces dossiers sont-ils ici ?</x-slot>

        <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
            <p>
                Un dossier arrive ici lorsqu'il a dépassé sa durée de conservation sans la
                moindre activité — ni changement de statut, ni note, ni message au candidat.
                <strong>Rien n'est supprimé à ce moment-là.</strong> Les pièces restent
                intactes sur le disque.
            </p>
            <p>
                Détruire des scans de passeports ne doit jamais reposer sur un oubli. C'est
                pourquoi une décision humaine est attendue, et pourquoi le bandeau du tableau
                de bord et le rappel mensuel ne peuvent être ni masqués, ni coupés.
            </p>
            <p>
                <strong>La contrepartie vous revient :</strong> tant que cette file n'est pas
                traitée, ces pièces d'identité restent stockées — ce que la règle de
                conservation cherchait précisément à éviter.
            </p>
            <p>
                Toute activité sur un dossier repousse son échéance : l'ouvrir et y intervenir
                suffit à le retirer d'ici.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
