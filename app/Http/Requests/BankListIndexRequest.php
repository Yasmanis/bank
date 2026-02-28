<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankListIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'hourly'   => 'nullable|in:am,pm',
            'status'   => 'nullable|string',
            'search'   => 'nullable|string',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'user_id'  => 'nullable|integer|exists:users,id',
            'name'     => 'nullable|string|max:50',
        ];
    }

    /**
     * Documentación para Scramble
     */
    public function queryParameters(): array
    {
        return [
            'user_id' => ['description' => 'Filtrar por un usuario específico.', 'example' => 5],
            'name'    => ['description' => 'Buscar por nombre del usuario.', 'example' => 'Jose'],
        ];
    }
}
