<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackApplicationRequest extends FormRequest
{
    /**
     * Le suivi est public : le couple référence + e-mail tient lieu de preuve.
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
        return [
            'reference' => ['required', 'string', 'max:32'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
