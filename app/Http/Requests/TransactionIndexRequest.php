<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @queryParam user_id int ID del cliente (rol "user") para filtrar sus movimientos. Example: 5
     * @queryParam status string Estado de la transacción (pending, approved, rejected). Example: approved
     * @queryParam type string Tipo de movimiento (income, outcome). Example: income
     * @queryParam per_page int Cantidad de registros por página. Example: 15
     */
    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = \App\Models\User::find($value);
                    if ($user && !$user->hasRole('user')) {
                        $fail('El usuario seleccionado debe ser un cliente (rol "user").');
                    }
                },
            ],

            'status' => 'nullable|string|in:pending,approved,rejected',

            'type' => 'nullable|string|in:income,outcome',

            'from' => 'nullable|date',

            'to' => 'nullable|date',
        ];
    }
}
