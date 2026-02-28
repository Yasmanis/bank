<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @queryParam bank_id int ID del banco al que se asigna la lista. Requerido si el status es approved.
     * @queryParam status string Estado de la validación (approved o denied).
     */
    public function rules(): array
    {
        return [
            'status' => 'required|in:approved,denied',
            'bank_id' => [
                'required_if:status,approved',
                'nullable',
                'exists:banks,id'
            ],
        ];
    }

    /**
     * Mensajes personalizados para la validación.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'El estado de la validación es obligatorio.',
            'status.in' => 'El estado debe ser "approved" o "denied".',
            'bank_id.required_if' => 'Debe asignar un banco para poder aprobar la lista.',
            'bank_id.exists' => 'El banco seleccionado no es válido.',
        ];
    }
}
