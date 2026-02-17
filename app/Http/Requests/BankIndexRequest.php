<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @queryParam search string Buscar por nombre del banco. Example: Banesco
     * @queryParam is_active boolean Filtrar por estado activo/inactivo. Example: true
     * @queryParam per_page int Registros por página. Example: 10
     */
    public function rules(): array
    {
        return [
            'search'    => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'per_page'  => 'nullable|integer|min:1|max:100',
        ];
    }
}
