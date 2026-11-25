<?php

namespace App\Http\Controllers;

use App\Models\Statement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Membuka PDF e-statement dari panel admin (tab baru, viewer PDF browser). */
class AdminStatementController extends Controller
{
    public function __invoke(Request $request, Statement $statement): StreamedResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        abort_unless(Storage::disk(Statement::DISK)->exists($statement->file_path), 404);

        return Storage::disk(Statement::DISK)->response(
            $statement->file_path,
            $statement->downloadName(),
            ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'],
        );
    }
}
