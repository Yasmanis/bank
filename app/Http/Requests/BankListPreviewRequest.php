<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankListPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para la previsualización y rectificación.
     *
     * @description El campo 'text' es obligatorio. 'date' y 'hourly' son opcionales
     * para permitir comparar la lista contra sorteos pasados (rectificación).
     */
    public function rules(): array
    {
        return [
            'text' => 'required|string|min:1',

            'hourly' => [
                'nullable',
                'string',
                'in:am,pm'
            ],

            'date' => [
                'nullable',
                'date',
                'date_format:Y-m-d'
            ],
        ];
    }

    /**
     * Documentación para Scramble / OpenAPI
     */
    public function bodyParameters(): array
    {
        return [
            'text' => [
                'description' => 'El contenido del chat de WhatsApp a procesar.',
                'example' => "25-10\n10,20-5\n05x10-20",
            ],
            'hourly' => [
                'description' => 'Horario del sorteo (am o pm). Si se envía, se intentará calcular premios.',
                'example' => 'am',
            ],
            'date' => [
                'description' => 'Fecha del sorteo (YYYY-MM-DD). Útil para rectificar listas de días anteriores.',
                'example' => now()->format('Y-m-d'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'Debe ingresar el texto de la lista.',
            'hourly.in' => 'El horario debe ser am o pm.',
            'date.date' => 'El formato de fecha no es válido.',
        ];
    }
}
