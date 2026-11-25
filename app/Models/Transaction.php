<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['account_id', 'ref', 'title', 'category', 'amount', 'direction', 'note', 'occurred_at'])]
class Transaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Nomor referensi unik, mis. TRX-20261121-8F3K2Q. */
    public static function newRef(): string
    {
        return 'TRX-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
