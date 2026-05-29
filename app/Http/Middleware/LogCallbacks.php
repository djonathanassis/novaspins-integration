<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogCallbacks
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('Callback received', [
            'path' => $request->path(),
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        return $next($request);
    }
}
