<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminConfigUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fixed' => 'sometimes|required|integer|min:1',
            'hundred' => 'sometimes|required|integer|min:1',
            'parlet' => 'sometimes|required|integer|min:1',
            'runner1' => 'sometimes|required|integer|min:1',
            'runner2' => 'sometimes|required|integer|min:1',
            'commission' => 'sometimes|required|numeric|between:0,100',
        ];
    }
}
