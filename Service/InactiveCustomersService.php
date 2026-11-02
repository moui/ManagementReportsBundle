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

use Doctrine\DBAL\Connection;

/**
 * Inactive customers: visible customers without a finished timesheet in the
 * last N days, or never. Parameterized DBAL — only the cutoff date is bound.
 */
final class InactiveCustomersService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array{id: int, name: string, lastEntry: \DateTimeImmutable|null, days: int|null}>
     */
    public function getRows(\DateTimeImmutable $today, int $inactiveDays): array
    {
        $cutoff = $today->modify('-' . $inactiveDays . ' days')->format('Y-m-d');

        $result = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.name, MAX(t.date_tz) AS last_entry
             FROM kimai2_customers c
             LEFT JOIN kimai2_projects p ON p.customer_id = c.id
             LEFT JOIN kimai2_timesheet t ON t.project_id = p.id AND t.end_time IS NOT NULL
             WHERE c.visible = 1
             GROUP BY c.id, c.name
             HAVING last_entry IS NULL OR last_entry <= :cutoff',
            ['cutoff' => $cutoff]
        );

        /** @var list<array{id: int|string, name: string, last_entry: string|null}> $result */
        $rows = [];
        foreach ($result as $row) {
            $last = $row['last_entry'] !== null ? new \DateTimeImmutable((string) $row['last_entry'], $today->getTimezone()) : null;
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'lastEntry' => $last,
                'days' => $last !== null ? (int) $last->diff($today)->days : null,
            ];
        }

        // Longest inactive first; customers that never had hours at the end.
        usort($rows, static fn (array $a, array $b): int => [$a['days'] === null ? 1 : 0, -($a['days'] ?? 0), $a['name']] <=> [$b['days'] === null ? 1 : 0, -($b['days'] ?? 0), $b['name']]);

        return $rows;
    }
}
