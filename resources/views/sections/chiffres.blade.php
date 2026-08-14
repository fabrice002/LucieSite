@php
    $chiffres = $section->liste('chiffres');
@endphp

{{-- Bloc livré vide par défaut : seules des données réelles ont leur place ici. --}}
@if ($chiffres !== [])
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        @if ($titre = $section->valeur('titre'))
            <h2 class="text-2xl font-bold tracking-tight text-ink-strong sm:text-3xl">{{ $titre }}</h2>
        @endif

        <dl class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($chiffres as $chiffre)
                <div class="rounded-xl border border-line bg-surface-raised p-6 text-center">
                    <dt class="sr-only">{{ $chiffre['libelle'] ?? '' }}</dt>
                    <dd>
                        <span class="block text-3xl font-bold text-brand-text">{{ $chiffre['valeur'] ?? '' }}</span>
                        <span class="mt-2 block text-sm text-ink-muted">{{ $chiffre['libelle'] ?? '' }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
@endif
