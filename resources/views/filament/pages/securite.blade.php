<x-filament-panels::page>
    @if ($this->deuxFacteursActive())
        <x-filament::section>
            <x-slot name="heading">Double authentification active</x-slot>
            <x-slot name="description">
                Un code temporaire vous est demandé à chaque connexion.
            </x-slot>

            <div class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Les codes de récupération vous permettent de retrouver l'accès à votre compte
                    si vous perdez votre téléphone. Chaque code ne fonctionne qu'une seule fois.
                    Conservez-les dans un gestionnaire de mots de passe.
                </p>

                <x-filament::button wire:click="basculerCodes" color="gray" size="sm">
                    {{ $codesVisibles ? 'Masquer les codes de récupération' : 'Afficher les codes de récupération' }}
                </x-filament::button>

                @if ($codesVisibles)
                    <div class="grid gap-2 rounded-lg bg-gray-50 p-4 font-mono text-sm sm:grid-cols-2 dark:bg-white/5">
                        @foreach ($this->codesDeRecuperation() as $code)
                            <span class="select-all text-gray-700 dark:text-gray-200">{{ $code }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-filament::section>

    @elseif ($this->enAttenteDeConfirmation())
        <x-filament::section>
            <x-slot name="heading">Terminez l'activation</x-slot>
            <x-slot name="description">
                Scannez ce QR code avec votre application d'authentification, puis confirmez
                avec le code à 6 chiffres qu'elle affiche.
            </x-slot>

            <div class="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
                <div class="rounded-lg bg-white p-4">
                    {!! $this->qrCode() !!}
                </div>

                <div class="space-y-2">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Impossible de scanner ? Saisissez cette clé manuellement :
                    </p>
                    <code class="block rounded bg-gray-50 px-3 py-2 font-mono text-sm break-all select-all dark:bg-white/5">
                        {{ $this->cleManuelle() }}
                    </code>
                </div>
            </div>
        </x-filament::section>

    @else
        <x-filament::section>
            <x-slot name="heading">Double authentification désactivée</x-slot>
            <x-slot name="description">
                Votre compte n'est protégé que par son mot de passe.
            </x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Ce back-office donne accès à des scans de passeports et de diplômes.
                Activer la double authentification est vivement recommandé : même si votre
                mot de passe fuitait, un code temporaire resterait nécessaire pour entrer.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
