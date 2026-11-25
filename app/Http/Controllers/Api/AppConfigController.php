<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Identitas aplikasi untuk mobile. Publik: dipakai sebelum nasabah masuk. */
class AppConfigController extends Controller
{
    /** GET /api/app-config */
    public function show(): JsonResponse
    {
        $version = Setting::logoVersion();

        return response()->json([
            'data' => [
                'name' => Setting::appName(),
                'company' => Setting::companyName(),
                'logo' => $version ? "app-config/logo?v={$version}" : null, // relatif terhadap base URL API
            ],
        ]);
    }

    /** GET /api/app-config/logo */
    public function logo(): StreamedResponse
    {
        $path = Setting::logoPath();
        abort_unless($path, 404);

        return Storage::disk(Setting::DISK)->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
