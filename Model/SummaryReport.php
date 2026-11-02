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
 * Aggregations for the hours summary: the KPI cards, the chart datasets and
 * the ranking tables (concentration of hours per customer, project and
 * activity).
 */
final class SummaryReport
{
    private const PALETTE = ['#206bc4', '#2fb344', '#f76707', '#ae3ec9', '#d63939', '#17a2b8', '#f59f00', '#4263eb', '#0ca678', '#e8590c',
        '#d6336c', '#74b816', '#1098ad', '#7048e8', '#fab005', '#495057', '#3bc9db', '#ff922b', '#94d82d', '#cc5de8',
        '#5c7cfa', '#20c997', '#fd7e14', '#868e96', '#a61e4d'];
    private const OTHERS_COLOR = '#adb5bd';

    private int $duration = 0;
    private int $billableDuration = 0;
    /** @var array<string, array<int|string, array{name: string, duration: int}>> */
    private array $groups = ['customer' => [], 'project' => [], 'user' => [], 'activity' => []];
    /** @var array<int, int> */
    private array $weekdays = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0];

    /**
     * @param list<SummaryEntry> $entries
     */
    public function __construct(private readonly SummaryFilter $filter, private readonly array $entries)
    {
        foreach ($entries as $entry) {
            $this->duration += $entry->duration;
            if ($entry->billable) {
                $this->billableDuration += $entry->duration;
            }
            $this->add('customer', $entry->customerId, $entry->customerName, $entry->duration);
            $this->add('project', $entry->projectId, $entry->getProjectLabel(), $entry->duration);
            $this->add('user', $entry->userId, $entry->userName, $entry->duration);
            $this->add('activity', $entry->activityId, $entry->activityName, $entry->duration);
            $this->weekdays[(int) $entry->date->format('N')] += $entry->duration;
        }
    }

    public function getFilter(): SummaryFilter
    {
        return $this->filter;
    }

    /**
     * @return list<SummaryEntry>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * KPI cards, translation key => formatted value.
     *
     * @return array<string, string>
     */
    public function getKpis(): array
    {
        $entries = \count($this->entries);
        $users = \count($this->groups['user']);
        $top = $this->ranking('customer')[0] ?? null;

        return [
            'management_reports.summary.kpi.hours' => Hours::format($this->duration),
            'management_reports.summary.kpi.billable_percent' => Hours::formatPercent(Hours::percent($this->billableDuration, $this->duration)),
            'management_reports.summary.kpi.hours_per_day' => Hours::format(intdiv($this->duration, $this->filter->getDays())),
            'management_reports.summary.kpi.entries' => Hours::number($entries, 0),
            'management_reports.summary.kpi.active_users' => (string) $users,
            'management_reports.summary.kpi.active_customers' => (string) \count($this->groups['customer']),
            'management_reports.summary.kpi.active_projects' => (string) \count($this->groups['project']),
            'management_reports.summary.kpi.used_activities' => (string) \count($this->groups['activity']),
            'management_reports.summary.kpi.entries_per_user' => $users > 0 ? Hours::number($entries / $users, 1) : '—',
            'management_reports.summary.kpi.top_customer_share' => Hours::formatPercent($top === null ? null : Hours::percent($top['duration'], $this->duration)),
        ];
    }

    public function getDurationFormatted(): string
    {
        return Hours::format($this->duration);
    }

    public function getBillableFormatted(): string
    {
        return Hours::format($this->billableDuration);
    }

    public function getNonBillableFormatted(): string
    {
        return Hours::format($this->duration - $this->billableDuration);
    }

    public function getTopCustomerName(): ?string
    {
        return $this->ranking('customer')[0]['name'] ?? null;
    }

    /**
     * Full ranking for a table: name, hours, % of the total.
     *
     * @return list<array{name: string, duration: string, hours: float, percent: string}>
     */
    public function getRanking(string $group): array
    {
        return array_map(fn (array $row): array => [
            'name' => $row['name'],
            'duration' => Hours::format($row['duration']),
            'hours' => Hours::decimal($row['duration']),
            'percent' => Hours::formatPercent(Hours::percent($row['duration'], $this->duration)),
        ], $this->ranking($group));
    }

    /**
     * Chart dataset (Kimai's charts macros): top N plus one "others" slice
     * labelled $othersLabel when $topN is given, everything otherwise.
     *
     * @return array{labels: list<string>, data: list<array{value: float, label: string, name: string, color: string}>}
     */
    public function getChart(string $group, ?int $topN = null, string $othersLabel = 'Others'): array
    {
        $rows = $this->ranking($group);

        if ($topN !== null && \count($rows) > $topN) {
            $others = array_sum(array_column(\array_slice($rows, $topN), 'duration'));
            $rows = \array_slice($rows, 0, $topN);
            $rows[] = ['name' => $othersLabel, 'duration' => $others, 'color' => self::OTHERS_COLOR];
        }

        return $this->chart($rows);
    }

    /**
     * @return array{labels: list<string>, data: list<array{value: float, label: string, name: string, color: string}>}
     */
    public function getBillableChart(string $billableLabel, string $nonBillableLabel): array
    {
        return $this->chart([
            ['name' => $billableLabel, 'duration' => $this->billableDuration, 'color' => '#2fb344'],
            ['name' => $nonBillableLabel, 'duration' => $this->duration - $this->billableDuration, 'color' => '#adb5bd'],
        ]);
    }

    /**
     * Monday to Sunday, always all seven days.
     *
     * @param array<int, string> $dayNames ISO weekday (1 = Monday) => label
     * @return array{labels: list<string>, data: list<array{value: float, label: string, name: string, color: string}>}
     */
    public function getWeekdayChart(array $dayNames): array
    {
        $rows = [];
        foreach ($this->weekdays as $day => $duration) {
            $rows[] = ['name' => $dayNames[$day] ?? (string) $day, 'duration' => $duration, 'color' => '#206bc4'];
        }

        return $this->chart($rows);
    }

    private function add(string $group, int $id, string $name, int $duration): void
    {
        $this->groups[$group][$id] ??= ['name' => $name, 'duration' => 0];
        $this->groups[$group][$id]['duration'] += $duration;
    }

    /**
     * @return list<array{name: string, duration: int}>
     */
    private function ranking(string $group): array
    {
        $rows = array_values($this->groups[$group]);
        usort($rows, static fn (array $a, array $b): int => [$b['duration'], $a['name']] <=> [$a['duration'], $b['name']]);

        return $rows;
    }

    /**
     * @param list<array{name: string, duration: int, color?: string}> $rows
     * @return array{labels: list<string>, data: list<array{value: float, label: string, name: string, color: string}>}
     */
    private function chart(array $rows): array
    {
        $labels = [];
        $data = [];
        foreach ($rows as $index => $row) {
            $labels[] = $row['name'];
            $data[] = [
                'value' => Hours::decimal($row['duration']),
                'label' => Hours::format($row['duration']) . ' h',
                'name' => $row['name'],
                'color' => $row['color'] ?? self::PALETTE[$index % \count(self::PALETTE)],
            ];
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
