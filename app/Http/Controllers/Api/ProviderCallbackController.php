<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\CallbackRequest;
use App\Services\Callbacks\Contracts\CallbackHandler;
use App\Services\Callbacks\Data\CallbackData;
use Illuminate\Http\JsonResponse;

readonly class ProviderCallbackController
{
    /**
     * @param array<string, CallbackHandler> $handlers
     */
    public function __construct(
        private array $handlers,
    ) {
    }

    public function __invoke(CallbackRequest $callback): JsonResponse
    {
        $data = CallbackData::fromArray($callback->validated());

        if (! isset($this->handlers[$data->type->value])) {
            return response()->json(['error' => 'Unknown type: ' . $data->type->value], 422);
        }

        return $this->handlers[$data->type->value]->handle($data);
    }
}
