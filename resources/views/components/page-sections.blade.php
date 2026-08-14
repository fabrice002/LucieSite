@props(['page'])

{{-- Empile les blocs publiés de la page, dans l'ordre choisi par la cliente.

     Un type inconnu du code, ou dont le partial n'existe pas, est ignoré
     silencieusement : la page reste debout même si un bloc a été retiré. --}}
@foreach (App\Models\PageSection::pour($page) as $section)
    @php($vue = $section->sectionType()?->view())

    @if ($vue && view()->exists($vue))
        @include($vue, ['section' => $section])
    @endif
@endforeach
