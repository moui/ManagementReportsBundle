<?php

declare(strict_types=1);

/*
 * This file is part of the "ManagementReportsBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ManagementReportsBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use KimaiPlugin\ManagementReportsBundle\Model\Hours;
use KimaiPlugin\ManagementReportsBundle\Model\SummaryEntry;
use KimaiPlugin\ManagementReportsBundle\Model\SummaryFilter;
use KimaiPlugin\ManagementReportsBundle\Service\Csv;
use KimaiPlugin\ManagementReportsBundle\Service\SummaryService;
use KimaiPlugin\ManagementReportsBundle\Service\Weekdays;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Hours summary: KPIs, charts and rankings for any date range and filter,
 * scoped to the users the viewer may see.
 */
#[Route(path: '/reporting/management/summary')]
#[IsGranted('view_reporting')]
#[IsGranted('management_reports')]
final class SummaryController extends AbstractController
{
    private const DEFAULT_DAYS = 30;
    private const DEFAULT_TOP = 10;

    public function __construct(
        private readonly SummaryService $summary,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '', name: 'management_reports_summary', methods: ['GET'])]
    public function report(Request $request): Response
    {
        $viewer = $this->getCurrentUser();
        $options = $this->summary->getOptions($viewer);
        [$filter, $error] = $this->resolveFilter($request, $viewer, $options);
        $report = $this->summary->getReport($viewer, $filter);

        return $this->render('@ManagementReports/summary.html.twig', [
            'report_title' => 'management_reports.summary.title',
            'report' => $report,
            'filter' => $filter,
            'options' => $options,
            'error' => $error,
            'query' => $filter->toQuery(),
            'presets' => $this->presets($viewer),
            'weekdays' => Weekdays::names($request->getLocale()),
        ]);
    }

    #[Route(path: '/export', name: 'management_reports_summary_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $viewer = $this->getCurrentUser();
        [$filter] = $this->resolveFilter($request, $viewer, $this->summary->getOptions($viewer));
        $entries = $this->summary->getEntries($viewer, $filter);
        $t = $this->translator;
        $yes = $t->trans('yes');
        $no = $t->trans('no');

        return Csv::response(
            \sprintf('kimai_hours_summary_%s_%s.csv', $filter->from->format('Y-m-d'), $filter->to->format('Y-m-d')),
            array_map(static fn (string $key): string => $t->trans($key), ['date', 'customer', 'project', 'activity', 'user', 'hours', 'billable', 'description']),
            array_map(static fn (SummaryEntry $e): array => [
                $e->date->format('Y-m-d'),
                $e->customerName,
                $e->getProjectLabel(),
                $e->activityName,
                $e->userName,
                Hours::decimal($e->duration),
                $e->billable ? $yes : $no,
                $e->description,
            ], $entries)
        );
    }

    /**
     * @param array{users: array<int, string>, customers: array<int, string>, projects: array<int, string>, activities: array<int, string>} $options
     * @return array{0: SummaryFilter, 1: string|null} the filter and an error translation key
     */
    private function resolveFilter(Request $request, User $viewer, array $options): array
    {
        $timezone = new \DateTimeZone($viewer->getTimezone());
        $today = new \DateTimeImmutable('today', $timezone);
        $from = $this->parseDate((string) $request->query->get('from', ''), $timezone);
        $to = $this->parseDate((string) $request->query->get('to', ''), $timezone);
        $error = null;

        if ($from === false || $to === false) {
            $error = 'management_reports.summary.error.invalid_date';
            $from = $to = null;
        }
        $to ??= $today;
        $from ??= $to->modify('-' . self::DEFAULT_DAYS . ' days');
        if ($from > $to) {
            $error = 'management_reports.summary.error.date_order';
            [$from, $to] = [$today->modify('-' . self::DEFAULT_DAYS . ' days'), $today];
        }

        $query = $request->query->all();
        $top = (int) ($query['top'] ?? self::DEFAULT_TOP);

        return [new SummaryFilter(
            $from,
            $to,
            // Only ids the viewer can pick are kept: a team lead cannot widen
            // the scope by posting another user's id.
            $this->ids($query['users'] ?? [], $options['users']),
            $this->ids($query['customers'] ?? [], $options['customers']),
            $this->ids($query['projects'] ?? [], $options['projects']),
            $this->ids($query['activities'] ?? [], $options['activities']),
            min(25, max(5, $top)),
        ), $error];
    }

    /**
     * null when empty, false when malformed.
     */
    private function parseDate(string $value, \DateTimeZone $timezone): \DateTimeImmutable|false|null
    {
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : false;
    }

    /**
     * @param array<int, string> $allowed
     * @return list<int>
     */
    private function ids(mixed $values, array $allowed): array
    {
        $ids = array_map('intval', array_filter((array) $values, static fn ($v): bool => \is_int($v) || (\is_string($v) && ctype_digit($v))));

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => \array_key_exists($id, $allowed))));
    }

    /**
     * Translation key => date range.
     *
     * @return array<string, array{from: string, to: string}>
     */
    private function presets(User $viewer): array
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone($viewer->getTimezone()));
        $lastMonth = $today->modify('first day of last month');

        return [
            'management_reports.summary.preset.last_30_days' => ['from' => $today->modify('-30 days')->format('Y-m-d'), 'to' => $today->format('Y-m-d')],
            'management_reports.summary.preset.this_month' => ['from' => $today->modify('first day of this month')->format('Y-m-d'), 'to' => $today->format('Y-m-d')],
            'management_reports.summary.preset.last_month' => ['from' => $lastMonth->format('Y-m-d'), 'to' => $lastMonth->modify('last day of this month')->format('Y-m-d')],
            'management_reports.summary.preset.this_year' => ['from' => $today->modify('first day of january this year')->format('Y-m-d'), 'to' => $today->format('Y-m-d')],
            'management_reports.summary.preset.last_180_days' => ['from' => $today->modify('-180 days')->format('Y-m-d'), 'to' => $today->format('Y-m-d')],
        ];
    }

    private function getCurrentUser(): User
    {
        // Kimai's getUser() already denies access without a logged-in user.
        return $this->getUser();
    }
}
