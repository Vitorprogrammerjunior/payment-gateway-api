<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'priority'  => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
