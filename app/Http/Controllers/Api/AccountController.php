<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Http\Resources\PayeeResource;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    /** GET /api/accounts/me */
    public function me(Request $request): AccountResource
    {
        $account = $request->user()->account()->with('user')->firstOrFail();

        return new AccountResource($account);
    }

    /** GET /api/payees */
    public function payees(Request $request): AnonymousResourceCollection
    {
        return PayeeResource::collection(
            $request->user()->payees()->orderBy('name')->get()
        );
    }

    /** GET /api/transactions — mutasi terbaru dulu (maks. 100). */
    public function transactions(Request $request): AnonymousResourceCollection
    {
        $account = $request->user()->account()->firstOrFail();

        return TransactionResource::collection(
            $account->transactions()
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
        );
    }
}
