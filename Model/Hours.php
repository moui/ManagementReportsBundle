<?php

declare(strict_types=1);

/*
 * This file is part of the "ManagementReportsBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ManagementReportsBundle\Model;

/**
 * Formatting lives in PHP on purpose: Twig macros imported in a block are out
 * of scope inside `{% embed %}`, and with strict_variables off that fails
 * silently with empty cells.
 *
 * Numbers follow the PHP default locale, which Symfony sets to the request
 * locale.
 */
final class Hours
{
    public static function format(int $seconds): string
    {
        $sign = $seconds < 0 ? '-' : '';
        $seconds = abs($seconds);

        return \sprintf('%s%d:%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    public static function decimal(int $seconds): float
    {
        return round($seconds / 3600, 2);
    }

    /**
     * Share of $part in $total, null when the total is zero.
     */
    public static function percent(float|int $part, float|int $total): ?float
    {
        if ((float) $total === 0.0) {
            return null;
        }

        return round($part / $total * 100, 1);
    }

    public static function formatPercent(?float $value): string
    {
        return $value === null ? '—' : self::number($value, 1) . ' %';
    }

    /**
     * Locale-aware number with a fixed number of decimals.
     */
    public static function number(float|int $value, int $decimals): string
    {
        $formatter = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        $result = $formatter->format($value);

        return $result === false ? number_format($value, $decimals) : $result;
    }
}
