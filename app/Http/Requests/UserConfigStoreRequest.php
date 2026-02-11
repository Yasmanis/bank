<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserConfigStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('user_config.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'exists:users,id',
                'unique:user_configs,user_id', // No duplicar config por usuario
                function ($attribute, $value, $fail) {
                    $user = \App\Models\User::find($value);
                    if ($user && !$user->hasRole('user')) {
                        $fail('Solo se puede asignar tarifas personalizadas a usuarios con rol "user".');
                    }
                },
            ],
            'fixed'      => 'required|integer|min:1',
            'hundred'    => 'required|integer|min:1',
            'parlet'     => 'required|integer|min:1',
            'triplet'    => 'required|integer|min:1',
            'runner1'    => 'required|integer|min:1',
            'runner2'    => 'required|integer|min:1',
            'commission' => 'required|numeric|between:0,100',
        ];
    }
}
