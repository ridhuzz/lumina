<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bahasa pesan API mengikuti header Accept-Language dari aplikasi:
 * "id..." -> Indonesia, bahasa lain -> English. Tanpa header -> default APP_LOCALE.
 * Terjemahan Inggris ada di lang/en.json.
 */
class SetLocaleFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Accept-Language');

        if ($header) {
            app()->setLocale(str_starts_with(strtolower(trim($header)), 'id') ? 'id' : 'en');
        }

        return $next($request);
    }
}
