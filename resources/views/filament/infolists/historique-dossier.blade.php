@if (empty($entrees))
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Aucun événement enregistré pour ce dossier.
    </p>
@else
    <ol class="space-y-0">
        @foreach ($entrees as $entree)
            <li class="flex gap-4 border-b border-gray-100 py-3 last:border-0 dark:border-white/10">
                <span class="w-40 shrink-0 text-sm text-gray-500 dark:text-gray-400">
                    {{ $entree['date']->translatedFormat('j M Y à H:i') }}
                </span>

                <span class="flex-1">
                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $entree['action'] }}
                        @if ($entree['detail'])
                            <span class="font-normal text-gray-500 dark:text-gray-400">
                                — {{ $entree['detail'] }}
                            </span>
                        @endif
                    </span>

                    <span class="block text-sm text-gray-500 dark:text-gray-400">
                        par {{ $entree['auteur'] }}
                    </span>
                </span>
            </li>
        @endforeach
    </ol>
@endif
