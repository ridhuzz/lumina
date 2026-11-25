<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StatementResource;
use App\Models\Statement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementController extends Controller
{
    /** GET /api/statements — periode terbaru dulu. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $account = $request->user()->account()->firstOrFail();

        return StatementResource::collection(
            $account->statements()->orderByDesc('year')->orderByDesc('month')->get()
        );
    }

    /** GET /api/statements/{id}/file — PDF, hanya untuk pemilik rekening. */
    public function file(Request $request, int $id): StreamedResponse
    {
        $account = $request->user()->account()->firstOrFail();

        // Dicari lewat rekening milik user: e-statement orang lain = 404 (tidak bocor ada/tidaknya).
        $statement = $account->statements()->find($id);
        abort_unless(
            $statement && Storage::disk(Statement::DISK)->exists($statement->file_path),
            404,
            __('E-statement tidak ditemukan.'),
        );
        $statement->setRelation('account', $account);

        return Storage::disk(Statement::DISK)->response(
            $statement->file_path,
            $statement->downloadName(),
            ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'],
        );
    }
}
