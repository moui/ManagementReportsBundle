<?php

declare(strict_types=1);

/*
 * This file is part of the "ManagementReportsBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ManagementReportsBundle\Service;

/**
 * Localized weekday names, Monday (1) to Sunday (7).
 */
final class Weekdays
{
    /**
     * @param string $pattern ICU pattern: 'EEEE' for full names, 'EEE' for short ones
     * @return array<int, string>
     */
    public static function names(string $locale, string $pattern = 'EEEE'): array
    {
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', \IntlDateFormatter::GREGORIAN, $pattern);
        // 2024-01-01 was a Monday.
        $monday = new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC'));

        $names = [];
        for ($day = 1; $day <= 7; ++$day) {
            $name = $formatter->format($monday->modify('+' . ($day - 1) . ' days'));
            $names[$day] = $name === false ? (string) $day : mb_convert_case($name, MB_CASE_TITLE);
        }

        return $names;
    }
}
