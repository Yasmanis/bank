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
            'text'   => 'required_without:file|nullable|string',
            'hourly' => 'required|in:am,pm',
            'client_uuid' => 'nullable|string', // El APK generará un UUID
            'client_created_at' => 'nullable|date',        // El APK mandará su hora local
            'file' => 'required_without:text|nullable|file|mimes:jpg,jpeg,png|max:5120',
        ];
    }

    /**
     * Opcional: Personalizar los mensajes de error.
     */
    public function messages(): array
    {
        return [
            'text.required' => 'El campo de texto es obligatorio.',
            'text.string' => 'El formato del texto no es válido.',
            'text.min' => 'El texto es demasiado corto para ser procesado.',
            'hourly.required' => 'Debes seleccionar un horario.',
            'hourly.in' => 'El horario debe ser am o pm.',
            'file.mimes' => 'El archivo debe ser una imagen (jpg, png) o un documento de texto (txt).',
            'file.max' => 'El archivo no puede pesar más de 5MB.',
            'text.required_without' => 'Debe enviar el texto de la lista o un archivo adjunto.',
        ];
    }
}
