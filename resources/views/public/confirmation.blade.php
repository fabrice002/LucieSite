<x-public-layout :title="content('confirmation.meta_titre', content('confirmation.titre'))">
    <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <div class="rounded-lg border border-green-200 bg-green-50 p-6 text-center">
            <svg class="mx-auto size-12 text-green-600" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>

            <h1 class="mt-4 text-2xl font-bold tracking-tight text-green-900">
                {{ content('confirmation.titre', 'Votre dossier a bien été reçu') }}
            </h1>

            <p class="mt-3 leading-relaxed text-green-800">
                {{ content('confirmation.intro') }}
            </p>
        </div>

        <div class="mt-8 rounded-lg border border-slate-200 p-6 text-center">
            <p class="text-sm font-medium tracking-wide text-slate-500 uppercase">
                {{ content('confirmation.label_reference', 'Votre référence de suivi') }}
            </p>

            <p class="mt-2 font-mono text-3xl font-bold tracking-wider text-brand-800 select-all">
                {{ $reference }}
            </p>
        </div>

        <p class="mt-8 text-center leading-relaxed text-slate-600">
            {{ content('confirmation.suite') }}
        </p>

        <div class="mt-8 flex flex-wrap justify-center gap-4">
            <a href="{{ route('suivi.index') }}"
               class="rounded-md bg-brand-700 px-6 py-3 font-medium text-white transition hover:bg-brand-800">
                {{ content('confirmation.bouton_suivi', 'Suivre mon dossier') }}
            </a>

            <a href="{{ route('home') }}"
               class="rounded-md border border-slate-300 px-6 py-3 font-medium text-slate-700 transition hover:border-brand-600 hover:text-brand-700">
                {{ content('confirmation.bouton_accueil', "Retour à l'accueil") }}
            </a>
        </div>
    </div>
</x-public-layout>
