@php
    $searched = $searched ?? false;
    $status = $status ?? null;
    $updatedAt = $updatedAt ?? null;
    $inputClass = 'mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-base shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-200 focus:outline-none';
@endphp

<x-public-layout
    :title="content('suivi.meta_titre', content('suivi.titre'))"
    :description="content('suivi.meta_description')"
>
    <div class="mx-auto max-w-xl px-4 py-12 sm:px-6">
        <h1 class="text-3xl font-bold tracking-tight text-brand-900">
            {{ content('suivi.titre', 'Suivre mon dossier') }}
        </h1>

        <p class="mt-4 leading-relaxed text-slate-600">
            {{ content('suivi.introduction') }}
        </p>

        <form method="POST" action="{{ route('suivi.show') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="reference" class="block text-sm font-medium text-slate-800">
                    {{ content('suivi.label_reference', 'Référence du dossier') }}
                </label>
                <input type="text" id="reference" name="reference" required
                       value="{{ old('reference') }}" class="{{ $inputClass }} font-mono">
                <p class="mt-1 text-xs text-slate-500">{{ content('suivi.aide_reference') }}</p>
                @error('reference')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-800">
                    {{ content('suivi.label_email', 'Adresse e-mail') }}
                </label>
                <input type="email" id="email" name="email" required inputmode="email"
                       value="{{ old('email') }}" class="{{ $inputClass }}">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <button type="submit"
                    class="w-full rounded-md bg-brand-700 px-6 py-3 font-medium text-white transition hover:bg-brand-800">
                {{ content('suivi.bouton', 'Consulter mon dossier') }}
            </button>
        </form>

        @if ($searched)
            @if ($status !== null)
                {{-- Uniquement le statut et la date : aucun document, aucune note interne. --}}
                <div role="status" class="mt-10 rounded-lg border border-slate-200 p-6">
                    <h2 class="text-lg font-semibold text-brand-900">
                        {{ content('suivi.resultat_titre', 'État de votre dossier') }}
                    </h2>

                    <dl class="mt-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <dt class="text-sm text-slate-500">
                                {{ content('suivi.resultat_statut', 'Statut') }}
                            </dt>
                            <dd class="rounded-full bg-brand-100 px-3 py-1 text-sm font-medium text-brand-800">
                                {{ $status->label() }}
                            </dd>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <dt class="text-sm text-slate-500">
                                {{ content('suivi.resultat_maj', 'Dernière mise à jour') }}
                            </dt>
                            <dd class="text-sm text-slate-800">
                                {{ $updatedAt?->translatedFormat('j F Y') }}
                            </dd>
                        </div>
                    </dl>
                </div>
            @else
                <div role="status" class="mt-10 rounded-lg border border-amber-200 bg-amber-50 p-6">
                    <p class="text-amber-900">{{ content('suivi.introuvable') }}</p>
                    <p class="mt-2 text-sm text-amber-800">{{ content('suivi.aide_contact') }}</p>
                </div>
            @endif
        @endif
    </div>
</x-public-layout>
