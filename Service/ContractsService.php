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

use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\ManagementReportsBundle\Model\Hours;

/**
 * Work contracts: the work_monday … work_sunday preferences of every
 * tracked user, with the contract type Kimai's working-time calculation uses.
 */
final class ContractsService
{
    /**
     * User preference => ISO weekday (1 = Monday).
     */
    public const DAYS = [
        'work_monday' => 1,
        'work_tuesday' => 2,
        'work_wednesday' => 3,
        'work_thursday' => 4,
        'work_friday' => 5,
        'work_saturday' => 6,
        'work_sunday' => 7,
    ];

    /**
     * Contract mode => translation key.
     */
    private const CONTRACT_TYPES = [
        'day' => 'management_reports.contracts.type.day',
        'week' => 'management_reports.contracts.type.week',
        'none' => 'management_reports.contracts.type.none',
    ];

    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * Enabled, non-system users without an admin role (the people whose
     * working time is tracked), largest weekly hours first.
     *
     * `contractType` and `warning` are translation keys; `contractType` falls
     * back to the raw mode id for modes this plugin does not know.
     *
     * @return list<array{user: User, days: array<string, string>, dayHours: array<string, float>, weekly: string, weeklyHours: float, weeklySeconds: int, contractType: string, warning: string|null}>
     */
    public function getRows(): array
    {
        $rows = [];

        foreach ($this->users->findAll() as $user) {
            if (!$user->isEnabled() || $user->isSystemAccount() || $user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_SUPER_ADMIN')) {
                continue;
            }

            $days = [];
            $dayHours = [];
            $weekly = 0;
            foreach (self::DAYS as $preference => $day) {
                $seconds = (int) $user->getPreferenceValue($preference, 0, false);
                $weekly += $seconds;
                $days[$preference] = Hours::format($seconds);
                $dayHours[$preference] = Hours::decimal($seconds);
            }

            $mode = $user->getWorkContractMode();
            $warning = null;
            if ($weekly === 0) {
                $warning = 'management_reports.contracts.warning.no_hours';
            } elseif ($mode === 'none') {
                // Kimai's working-time calculation ignores the daily hours
                // without a contract type: expected hours would be 0.
                $warning = 'management_reports.contracts.warning.no_contract_type';
            }

            $rows[] = [
                'user' => $user,
                'days' => $days,
                'dayHours' => $dayHours,
                'weekly' => Hours::format($weekly),
                'weeklyHours' => Hours::decimal($weekly),
                'weeklySeconds' => $weekly,
                'contractType' => self::CONTRACT_TYPES[$mode] ?? $mode,
                'warning' => $warning,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['weeklySeconds'], strtolower($a['user']->getDisplayName())] <=> [$a['weeklySeconds'], strtolower($b['user']->getDisplayName())]);

        return $rows;
    }
}
