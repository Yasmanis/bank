<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyNumberStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Ahora hundred acepta exactamente 1 dígito (ej: "1")
            'hundred' => 'required|string|size:1',

            // Los demás mantienen sus 2 dígitos (ej: "50", "05")
            'fixed'   => 'required|string|size:2',
            'runner1' => 'required|string|size:2',
            'runner2' => 'required|string|size:2',

            'date'    => 'required|date',

            'hourly'  => [
                'required',
                'in:am,pm',
                Rule::unique('daily_numbers', 'hourly')->where(function ($query) {
                    return $query->where('date', $this->date);
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'hundred.size'  => 'La centena debe ser un único dígito (0-9).',
            'fixed.size'    => 'El fijo debe tener 2 dígitos (ej: 05).',
            'hourly.unique' => 'Ya existe un resultado registrado para esta fecha y horario.',
            'hourly.in'     => 'El horario debe ser am o pm.',
        ];
    }
}
