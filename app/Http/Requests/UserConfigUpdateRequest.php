<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserConfigUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('user_config.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Obtenemos el ID del registro desde la ruta para la regla unique
        $configId = $this->route('user_config') ?? $this->route('id');

        return [
            // El user_id normalmente no se cambia, pero si se hace, validamos unicidad
            'user_id' => [
                'sometimes',
                'required',
                'exists:users,id',
                Rule::unique('user_configs', 'user_id')->ignore($configId),
            ],
            'fixed'      => 'sometimes|required|integer|min:1',
            'hundred'    => 'sometimes|required|integer|min:1',
            'parlet'     => 'sometimes|required|integer|min:1',
            'triplet'    => 'sometimes|required|integer|min:1',
            'runner1'    => 'sometimes|required|integer|min:1',
            'runner2'    => 'sometimes|required|integer|min:1',
            'commission' => 'sometimes|required|numeric|between:0,100',
        ];
    }
}
