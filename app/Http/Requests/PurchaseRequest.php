<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_name'          => ['required', 'string', 'max:255'],
            'client_email'         => ['required', 'email', 'max:255'],
            'card_number'          => ['required', 'string', 'digits:16'],
            'cvv'                  => ['required', 'string', 'digits_between:3,4'],
            'products'             => ['required', 'array', 'min:1'],
            'products.*.id'        => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity'  => ['required', 'integer', 'min:1'],
        ];
    }
}
