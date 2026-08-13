<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreApplicationRequest extends FormRequest
{
    /**
     * Le dépôt est public : aucun compte candidat n'existe.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // mimes inspecte le contenu réel du fichier, pas son extension : un
        // exécutable renommé en .pdf est donc rejeté.
        $file = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ().-]{6,}$/'],
            'country_of_residence' => ['required', 'string', 'max:255'],
            'target_program' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],

            'cv' => ['required', ...$file],
            'tcf_tef' => ['required', ...$file],
            'passeport' => ['nullable', ...$file],

            'diplomes' => ['nullable', 'array', 'max:10'],
            'diplomes.*' => $file,

            'autres' => ['nullable', 'array', 'max:10'],
            'autres.*' => $file,

            // Honeypot : invisible pour un humain, rempli par les robots.
            'website' => ['prohibited'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Le téléphone doit être un numéro valide, de préférence au format international (ex. +237 6 XX XX XX XX).',
            'website.prohibited' => 'Votre envoi a été refusé.',
        ];
    }

    /**
     * Get the candidate attributes to persist.
     *
     * @return array<string, string|null>
     */
    public function candidateAttributes(): array
    {
        /** @var array<string, string|null> $attributes */
        $attributes = $this->safe()->only([
            'first_name',
            'last_name',
            'email',
            'phone',
            'country_of_residence',
            'target_program',
            'message',
        ]);

        return $attributes;
    }

    /**
     * Get the uploaded documents, keyed by document type.
     *
     * @return array<string, list<UploadedFile>>
     */
    public function documents(): array
    {
        $mapping = [
            'cv' => DocumentType::Cv,
            'tcf_tef' => DocumentType::TcfTef,
            'passeport' => DocumentType::Passeport,
            'diplomes' => DocumentType::Diplome,
            'autres' => DocumentType::Autre,
        ];

        $documents = [];

        foreach ($mapping as $field => $type) {
            $files = array_values(array_filter(
                is_array($this->file($field)) ? $this->file($field) : [$this->file($field)],
                fn (mixed $file): bool => $file instanceof UploadedFile,
            ));

            if ($files !== []) {
                $documents[$type->value] = $files;
            }
        }

        /** @var array<string, list<UploadedFile>> $documents */
        return $documents;
    }
}
