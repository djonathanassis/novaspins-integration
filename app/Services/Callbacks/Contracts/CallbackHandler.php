<?php

declare(strict_types=1);

namespace App\Services\Callbacks\Contracts;

use App\Services\Callbacks\Data\CallbackData;
use Illuminate\Http\JsonResponse;

interface CallbackHandler
{
    public function handle(CallbackData $data): JsonResponse;
}
