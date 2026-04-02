<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the storefront catch-all route would otherwise handle a request for a real
 * file under public/ (css, js, fonts, …), return that file. Fixes unstyled Filament
 * and broken /build assets if the front controller receives those paths (e.g. tunnel / proxy).
 */
class ServePublicAssets
{
    /** @var list<string> */
    protected array $pathPrefixes = [
        'css/',
        'js/',
        'build/',
        'fonts/',
        'images/',
        'vendor/',
        'storage/',
        'livewire/',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() !== 'GET' && $request->method() !== 'HEAD') {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');

        foreach ($this->pathPrefixes as $prefix) {
            if (! str_starts_with($path, $prefix)) {
                continue;
            }

            $candidate = public_path($path);
            $publicRoot = realpath(public_path());
            $resolved = realpath($candidate);

            if ($publicRoot === false || $resolved === false || ! str_starts_with($resolved, $publicRoot)) {
                continue;
            }

            if (is_file($resolved)) {
                return response()->file($resolved);
            }
        }

        return $next($request);
    }
}
