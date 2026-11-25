<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Bentuk JSON sama dengan Statement.fromJson di aplikasi Flutter. */
class StatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'month' => $this->month,
            'period' => $this->periodLabel(),
            'size' => $this->size,
            'uploaded_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
