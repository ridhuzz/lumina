<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Bentuk JSON sama dengan Payee.fromJson di aplikasi Flutter. */
class PayeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'bank' => $this->bank,
            'number' => $this->number,
        ];
    }
}
