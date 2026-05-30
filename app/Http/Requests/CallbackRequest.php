<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:bet,win,rollback'],
            'player_external_id' => ['required', 'string'],
            'provider_transaction_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gte:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'original_transaction_id' => ['required_if:type,rollback', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
