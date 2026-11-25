<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Pratinjau gambar banner di panel admin (termasuk banner nonaktif). */
class AdminBannerImageController extends Controller
{
    public function __invoke(Request $request, Banner $banner): StreamedResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        abort_unless(Storage::disk(Banner::DISK)->exists($banner->image_path), 404);

        return Storage::disk(Banner::DISK)->response($banner->image_path);
    }
}
