@php
    $searched = $searched ?? false;
    $status = $status ?? null;
    $updatedAt = $updatedAt ?? null;
    $inputClass = 'mt-1 block w-full rounded-md border border-line bg-surface-raised px-3 py-2 text-base text-ink shadow-sm focus:border-brand focus:ring-2 focus:ring-brand-line focus:outline-none';
@endphp

<x-public-layout
    :title="content('suivi.meta_titre', content('suivi.titre'))"
    :description="content('suivi.meta_description')"
>
    <x-page-header
        :titre="content('suivi.titre', 'Suivre mon dossier')"
        :introduction="content('suivi.introduction')"
    />

    <div class="mx-auto max-w-xl px-4 py-12 sm:px-6">
        <form method="POST" action="{{ route('suivi.show') }}" class="space-y-5">
            @csrf

            <div>
                <label for="reference" class="block text-sm font-medium text-ink">
                    {{ content('suivi.label_reference', 'Référence du dossier') }}
                </label>
                <input type="text" id="reference" name="reference" required
                       value="{{ old('reference') }}" class="{{ $inputClass }} font-mono">
                <p class="mt-1 text-xs text-ink-muted">{{ content('suivi.aide_reference') }}</p>
                @error('reference')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-ink">
                    {{ content('suivi.label_email', 'Adresse e-mail') }}
                </label>
                <input type="email" id="email" name="email" required inputmode="email"
                       value="{{ old('email') }}" class="{{ $inputClass }}">
                @error('email')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <button type="submit"
                    class="w-full rounded-md bg-brand px-6 py-3 font-medium text-white transition hover:bg-brand-hover">
                {{ content('suivi.bouton', 'Consulter mon dossier') }}
            </button>
        </form>

        @if ($searched)
            @if ($status !== null)
                {{-- Uniquement le statut et la date : aucun document, aucune note interne. --}}
                <div role="status" class="mt-10 rounded-xl border border-line bg-surface-raised p-6">
                    <h2 class="text-lg font-semibold text-ink-strong">
                        {{ content('suivi.resultat_titre', 'État de votre dossier') }}
                    </h2>

                    <dl class="mt-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <dt class="text-sm text-ink-muted">
                                {{ content('suivi.resultat_statut', 'Statut') }}
                            </dt>
                            <dd class="rounded-full bg-brand-soft px-3 py-1 text-sm font-medium text-brand-text">
                                {{ $status->label() }}
                            </dd>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <dt class="text-sm text-ink-muted">
                                {{ content('suivi.resultat_maj', 'Dernière mise à jour') }}
                            </dt>
                            <dd class="text-sm text-ink">
                                {{ $updatedAt?->translatedFormat('j F Y') }}
                            </dd>
                        </div>
                    </dl>
                </div>
            @else
                <div role="status" class="mt-10 rounded-xl border border-notice-line bg-notice p-6">
                    <p class="text-notice-ink">{{ content('suivi.introuvable') }}</p>
                    <p class="mt-2 text-sm text-notice-ink opacity-80">{{ content('suivi.aide_contact') }}</p>
                </div>
            @endif
        @endif
    </div>
</x-public-layout>
