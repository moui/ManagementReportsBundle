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
 * One finished timesheet, flattened for aggregation and CSV.
 */
final class SummaryEntry
{
    public function __construct(
        public readonly \DateTimeImmutable $date,
        public readonly int $duration,
        public readonly bool $billable,
        public readonly string $description,
        public readonly int $userId,
        public readonly string $userName,
        public readonly int $customerId,
        public readonly string $customerName,
        public readonly int $projectId,
        public readonly string $projectName,
        public readonly int $activityId,
        public readonly string $activityName,
    ) {
    }

    public function getProjectLabel(): string
    {
        return $this->projectName . ' [' . $this->customerName . ']';
    }
}
