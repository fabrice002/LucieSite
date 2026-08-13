<x-filament-panels::page>
    @if (auth()->user()?->must_change_password)
        <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 dark:border-warning-700 dark:bg-warning-500/10">
            <p class="text-sm text-warning-800 dark:text-warning-300">
                Ce mot de passe vous a été communiqué par un administrateur : deux personnes
                le connaissent. Choisissez-en un nouveau, que vous seul connaîtrez.
            </p>
        </div>
    @endif

    <x-filament::section>
        <form wire:submit="enregistrer" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit">
                    Enregistrer le mot de passe
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
