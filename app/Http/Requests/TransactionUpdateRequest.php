<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            //es el usuario al que se le aplica la entrada o salida de saldo
            'user_id' => 'sometimes|required|exists:users,id',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'type' => 'sometimes|required|in:income,outcome',
            //Requerido para explicacion por que se edita
            'description' => 'sometimes|required|string|max:255',
            'date' => 'sometimes|required|date',
        ];
    }
}
