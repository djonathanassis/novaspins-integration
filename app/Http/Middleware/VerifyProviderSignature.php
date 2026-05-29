<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\HmacValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyProviderSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $validator = new HmacValidator((string) config('services.novaspins.hmac_secret'));

        if (! $validator->isValid($request->getContent(), $request->header('X-Signature'))) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        return $next($request);
    }
}
