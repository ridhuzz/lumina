<?php

namespace App\Support;

class Rupiah
{
    /** 12750000 -> "Rp 12.750.000" (tanpa desimal, pemisah ribuan titik). */
    public static function format(float|int|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '-';
        }

        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
