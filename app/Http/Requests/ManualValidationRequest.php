<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManualValidationRequest extends FormRequest
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
            'bank_id' => 'required|exists:banks,id',
            'fixed'   => 'required|numeric|min:0',
            'hundred' => 'required|numeric|min:0',
            'parlet'  => 'required|numeric|min:0',
            'triplet' => 'required|numeric|min:0',
            'runner1' => 'required|numeric|min:0',
            'runner2' => 'required|numeric|min:0',
            'total'   => 'required|numeric|min:0',
            'prizes'  => 'required|numeric|min:0'
        ];
    }
}
