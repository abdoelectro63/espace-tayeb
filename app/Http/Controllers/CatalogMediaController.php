<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogMediaController extends Controller
{
    /**
     * Serve product images that were stored on the default (private) disk before uploads used disk('public').
     */
    public function show(string $path): Response|StreamedResponse
    {
        $path = urldecode($path);

        if (str_contains($path, '..')) {
            abort(404);
        }

        if (! str_starts_with($path, 'products/')) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path);
    }
}
