<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessListRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            'text' => 'required|string|min:1',
            'hourly' => 'required|in:am,pm',
            'bank_id' => 'required|exists:banks,id',
        ];
    }

    /**
     * Opcional: Personalizar los mensajes de error.
     */
    public function messages(): array
    {
        return [
            'text.required' => 'El campo de texto es obligatorio.',
            'text.string'   => 'El formato del texto no es válido.',
            'text.min'      => 'El texto es demasiado corto para ser procesado.',
            'hourly.required' => 'Debes seleccionar un horario.',
            'hourly.in'       => 'El horario debe ser am o pm.',
        ];
    }
}
