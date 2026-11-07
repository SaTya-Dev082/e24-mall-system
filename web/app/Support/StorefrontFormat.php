<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class StorefrontFormat
{
    /** Display database decimal amounts without floating-point conversion. */
    public static function money(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '00');
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);

        return '$'.$grouped.'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    public static function date(DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->setTimezone('Asia/Phnom_Penh')->format('d M Y, H:i');
    }
}
