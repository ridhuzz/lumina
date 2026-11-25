<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Bentuk JSON sama dengan Transaction.fromJson di aplikasi Flutter. */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ref,
            'title' => $this->title,
            'category' => $this->category,
            'amount' => (float) $this->amount,
            'direction' => $this->direction,
            'time' => $this->occurred_at->toIso8601String(),
            'note' => $this->note,
        ];
    }
}
