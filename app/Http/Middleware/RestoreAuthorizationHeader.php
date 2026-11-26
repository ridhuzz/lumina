<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sebagian server Apache (tanpa .htaccess / PHP-FPM) membuang header
 * `Authorization` sebelum sampai ke PHP. Aplikasi mobile juga mengirim token di
 * `X-Authorization`; middleware ini menyalinnya kembali bila `Authorization` hilang.
 */
class RestoreAuthorizationHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->has('Authorization')) {
            $fallback = $request->headers->get('X-Authorization');
            if (is_string($fallback) && str_starts_with($fallback, 'Bearer ')) {
                $request->headers->set('Authorization', $fallback);
            }
        }

        return $next($request);
    }
}
