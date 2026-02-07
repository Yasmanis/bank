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
            'user_id' => 'sometimes|required|exists:users,id',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'type' => 'sometimes|required|in:income,outcome',
            'description' => 'sometimes|required|string|max:255',
            'date' => 'sometimes|required|date',
        ];
    }
}
