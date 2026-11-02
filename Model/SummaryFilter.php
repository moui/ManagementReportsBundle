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
 * Filters of the hours summary. Ids are validated against the option lists
 * by the controller before they get here.
 */
final class SummaryFilter
{
    /**
     * @param list<int> $users
     * @param list<int> $customers
     * @param list<int> $projects
     * @param list<int> $activities
     */
    public function __construct(
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to,
        public readonly array $users = [],
        public readonly array $customers = [],
        public readonly array $projects = [],
        public readonly array $activities = [],
        public readonly int $topN = 10,
    ) {
    }

    /**
     * Calendar days in the range, at least 1.
     */
    public function getDays(): int
    {
        return max(1, (int) $this->from->diff($this->to)->days + 1);
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return [
            'from' => $this->from->format('Y-m-d'),
            'to' => $this->to->format('Y-m-d'),
            'users' => $this->users,
            'customers' => $this->customers,
            'projects' => $this->projects,
            'activities' => $this->activities,
            'top' => $this->topN,
        ];
    }
}
