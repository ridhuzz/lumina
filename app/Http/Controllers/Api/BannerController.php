<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Banner promo untuk aplikasi. Publik (tanpa token): banner login tampil sebelum masuk. */
class BannerController extends Controller
{
    /** GET /api/banners?placement=welcome|home */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'placement' => ['required', Rule::in(array_keys(Banner::PLACEMENTS))],
        ]);

        $banners = Banner::query()->live()->where('placement', $data['placement'])->get();

        return response()->json([
            'data' => $banners->map(fn (Banner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'image' => $b->imagePath(), // relatif terhadap base URL API
                'link_url' => $b->link_url,
            ])->values(),
        ]);
    }

    /** GET /api/banners/{id}/image — hanya banner yang sedang tayang. */
    public function image(int $id): StreamedResponse
    {
        $banner = Banner::query()->live()->find($id);
        abort_unless($banner && Storage::disk(Banner::DISK)->exists($banner->image_path), 404);

        return Storage::disk(Banner::DISK)->response($banner->image_path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
