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

    public static function productAge(DateTimeInterface $date): string
    {
        $created = CarbonImmutable::instance($date)->setTimezone('Asia/Phnom_Penh');
        $now = CarbonImmutable::now('Asia/Phnom_Penh');
        if ($created->isAfter($now)) {
            return $created->format('j-M-Y');
        }
        $seconds = $now->getTimestamp() - $created->getTimestamp();
        if ($seconds < 3600) {
            return max(1, intdiv($seconds, 60)).'m';
        }
        if ($seconds < 86400) {
            return intdiv($seconds, 3600).'h';
        }
        if ($seconds < 604800) {
            return intdiv($seconds, 86400).'d';
        }
        if ($seconds < 2419200) {
            return intdiv($seconds, 604800).'w';
        }

        return $created->format('j-M-Y');
    }
}
