<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminConfigStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user_id = $request->user_id ?? auth()->id();
        $this->merge([
            'user_id' => $user_id,
        ]);
        return [
            // Es el campo es unico, toma por defecto el usuario autenticado, solo el super-admin tiene permiso de crear otras config
            'user_id' => 'required|exists:users,id|unique:admin_configs,user_id',
            'fixed' => 'required|integer|min:1',
            'hundred' => 'required|integer|min:1',
            'parlet' => 'required|integer|min:1',
            'runner1' => 'required|integer|min:1',
            'runner2' => 'required|integer|min:1',
            'triplet' => 'required|integer|min:1',
            'default_commission' => 'required|numeric|between:0,100',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'Este administrador ya tiene una configuración asignada.',
        ];
    }
}
