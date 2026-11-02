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
use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimesheetRepository;
use KimaiPlugin\ManagementReportsBundle\Model\SummaryEntry;
use KimaiPlugin\ManagementReportsBundle\Model\SummaryFilter;
use KimaiPlugin\ManagementReportsBundle\Model\SummaryReport;

/**
 * Data for the hours summary, read through DQL.
 */
final class SummaryService
{
    public function __construct(
        private readonly TimesheetRepository $timesheets,
        private readonly CustomerRepository $customers,
        private readonly ProjectRepository $projects,
        private readonly ActivityRepository $activities,
        private readonly ReportScope $scope,
    ) {
    }

    public function getReport(User $viewer, SummaryFilter $filter): SummaryReport
    {
        return new SummaryReport($filter, $this->getEntries($viewer, $filter));
    }

    /**
     * Finished timesheets in the range, filtered and scoped to the viewer.
     *
     * @return list<SummaryEntry>
     */
    public function getEntries(User $viewer, SummaryFilter $filter): array
    {
        $qb = $this->timesheets->createQueryBuilder('t');
        $qb
            ->select('t.date AS day', 't.duration AS duration', 't.billable AS billable', 't.description AS description')
            ->addSelect('u.id AS userId', 'u.alias AS alias', 'u.username AS username')
            ->addSelect('c.id AS customerId', 'c.name AS customerName', 'p.id AS projectId', 'p.name AS projectName')
            ->addSelect('a.id AS activityId', 'a.name AS activityName')
            ->join('t.user', 'u')
            ->join('t.project', 'p')
            ->join('p.customer', 'c')
            ->join('t.activity', 'a')
            ->where($qb->expr()->isNotNull('t.end'))
            ->andWhere($qb->expr()->between('t.date', ':from', ':to'))
            ->setParameter('from', $filter->from->format('Y-m-d'))
            ->setParameter('to', $filter->to->format('Y-m-d'))
            ->orderBy('t.date', 'ASC')
            ->addOrderBy('t.begin', 'ASC');

        $restrict = $this->scope->restrictToUserIds($viewer);
        if ($restrict !== null) {
            // An empty list must match nothing, not everything.
            $qb->andWhere($qb->expr()->in('u.id', ':scopeUsers'))->setParameter('scopeUsers', $restrict === [] ? [0] : $restrict);
        }

        foreach (['users' => 'u.id', 'customers' => 'c.id', 'projects' => 'p.id', 'activities' => 'a.id'] as $name => $field) {
            $ids = $filter->{$name};
            if ($ids !== []) {
                $qb->andWhere($qb->expr()->in($field, ':f_' . $name))->setParameter('f_' . $name, $ids);
            }
        }

        /** @var list<array{day: \DateTimeInterface, duration: int|null, billable: bool, description: string|null, userId: int, alias: string|null, username: string, customerId: int, customerName: string, projectId: int, projectName: string, activityId: int, activityName: string}> $result */
        $result = $qb->getQuery()->getArrayResult();
        $entries = [];
        foreach ($result as $row) {
            $alias = trim((string) ($row['alias'] ?? ''));
            $entries[] = new SummaryEntry(
                \DateTimeImmutable::createFromInterface($row['day']),
                (int) $row['duration'],
                (bool) $row['billable'],
                trim((string) ($row['description'] ?? '')),
                (int) $row['userId'],
                $alias !== '' ? $alias : (string) $row['username'],
                (int) $row['customerId'],
                (string) $row['customerName'],
                (int) $row['projectId'],
                (string) $row['projectName'],
                (int) $row['activityId'],
                (string) $row['activityName'],
            );
        }

        return $entries;
    }

    /**
     * Filter option lists: the viewer's visible users, and visible customers,
     * projects (of visible customers) and activities.
     *
     * @return array{users: array<int, string>, customers: array<int, string>, projects: array<int, string>, activities: array<int, string>}
     */
    public function getOptions(User $viewer): array
    {
        $users = [];
        foreach ($this->scope->visibleUsers($viewer) as $user) {
            if ($user->isEnabled()) {
                $users[(int) $user->getId()] = $user->getDisplayName();
            }
        }

        /** @var list<array{id: int, name: string}> $rows */
        $rows = $this->customers->createQueryBuilder('c')->select('c.id', 'c.name')->where('c.visible = true')->orderBy('c.name')->getQuery()->getArrayResult();
        $customers = [];
        foreach ($rows as $row) {
            $customers[(int) $row['id']] = (string) $row['name'];
        }

        $projects = [];
        $rows = $this->projects->createQueryBuilder('p')
            ->select('p.id', 'p.name', 'c.name AS customer')
            ->join('p.customer', 'c')
            ->where('p.visible = true')
            ->andWhere('c.visible = true')
            ->orderBy('c.name')
            ->addOrderBy('p.name')
            ->getQuery()
            ->getArrayResult();
        /** @var list<array{id: int, name: string, customer: string}> $rows */
        foreach ($rows as $row) {
            $projects[(int) $row['id']] = $row['name'] . ' [' . $row['customer'] . ']';
        }

        /** @var list<array{id: int, name: string}> $rows */
        $rows = $this->activities->createQueryBuilder('a')->select('a.id', 'a.name')->where('a.visible = true')->orderBy('a.name')->getQuery()->getArrayResult();
        $activities = [];
        foreach ($rows as $row) {
            $activities[(int) $row['id']] = (string) $row['name'];
        }

        return ['users' => $users, 'customers' => $customers, 'projects' => $projects, 'activities' => $activities];
    }
}
