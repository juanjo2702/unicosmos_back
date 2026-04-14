<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ParseJson
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $contentType = $request->header('Content-Type');
        Log::info('ParseJson middleware', ['route' => $request->path(), 'content-type' => $contentType, 'content' => $request->getContent()]);

        if (str_contains($contentType, 'application/json')) {
            $content = $request->getContent();
            if (! empty($content)) {
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    Log::info('Parsed JSON data', $data);
                    $request->merge($data);
                } else {
                    Log::warning('JSON decode error', ['error' => json_last_error_msg(), 'content' => $content]);
                }
            }
        }

        return $next($request);
    }
}
