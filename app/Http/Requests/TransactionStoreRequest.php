<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionStoreRequest extends FormRequest
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
            //es el usuario al que se le aplica la entrada o salida de saldo
            'user_id'     => 'required|exists:users,id',
            'amount'      => 'required|numeric|min:0.01',
            'type'        => 'required|in:income,outcome',
            'description' => 'required|string|max:255',
            'date'        => 'required|date',
        ];
    }
}
