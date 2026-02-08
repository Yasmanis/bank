<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyNumberUpdateRequest extends FormRequest
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
        $id = $this->route('id');

        return [
            'hundred' => 'sometimes|required|string|size:1',
            'fixed'   => 'sometimes|required|string|size:2',
            'runner1' => 'sometimes|required|string|size:2',
            'runner2' => 'required|string|size:2',
            //Campo unico para un mismo horario con hourly
            'date'    => 'sometimes|required|date',
            //Campo unico para un mismo horario con date
            'hourly'  => [
                'sometimes',
                'required',
                'in:am,pm',
                // Ignoramos el ID actual para que permita guardar si no cambiamos fecha/hora
                Rule::unique('daily_numbers', 'hourly')->where(function ($query) {
                    return $query->where('date', $this->date);
                })->ignore($id),
            ],
        ];
    }
}
