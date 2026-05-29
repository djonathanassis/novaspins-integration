<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'amount' => ['required', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'original_transaction_id' => ['nullable', 'string'],
        ];
    }
}
