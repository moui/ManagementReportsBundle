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
use KimaiPlugin\ManagementReportsBundle\Service\ContractsService;
use KimaiPlugin\ManagementReportsBundle\Service\Csv;
use KimaiPlugin\ManagementReportsBundle\Service\InactiveCustomersService;
use KimaiPlugin\ManagementReportsBundle\Service\Weekdays;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Organisation-wide reports: work contracts and inactive customers. Inactive
 * *projects* are covered by Kimai's own report.
 */
#[Route(path: '/reporting/management')]
#[IsGranted('view_reporting')]
#[IsGranted('management_reports_admin')]
final class AdminReportsController extends AbstractController
{
    private const INACTIVE_DAYS = [30, 60, 90, 180];
    private const DEFAULT_INACTIVE_DAYS = 60;

    public function __construct(
        private readonly ContractsService $contracts,
        private readonly InactiveCustomersService $inactiveCustomers,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/contracts', name: 'management_reports_contracts', methods: ['GET'])]
    public function contracts(Request $request): Response
    {
        $rows = $this->contracts->getRows();
        $names = Weekdays::names($request->getLocale(), 'EEE');

        return $this->render('@ManagementReports/contracts.html.twig', [
            'report_title' => 'management_reports.contracts.title',
            'rows' => $rows,
            'days' => array_map(static fn (int $day): string => $names[$day], array_values(ContractsService::DAYS)),
            'warnings' => \count(array_filter($rows, static fn (array $row): bool => $row['warning'] !== null)),
        ]);
    }

    #[Route(path: '/contracts/export', name: 'management_reports_contracts_export', methods: ['GET'])]
    public function contractsExport(Request $request): Response
    {
        $names = Weekdays::names($request->getLocale());
        $t = $this->translator;

        return Csv::response(
            'kimai_work_contracts.csv',
            array_merge(
                [$t->trans('user'), $t->trans('email')],
                array_map(static fn (int $day): string => $names[$day], array_values(ContractsService::DAYS)),
                [$t->trans('management_reports.contracts.weekly_hours'), $t->trans('management_reports.contracts.contract_type'), $t->trans('management_reports.contracts.warning')]
            ),
            array_map(static fn (array $row): array => array_merge(
                [$row['user']->getDisplayName(), $row['user']->getEmail()],
                array_values($row['dayHours']),
                [$row['weeklyHours'], $t->trans($row['contractType']), $row['warning'] !== null ? $t->trans($row['warning']) : '']
            ), $this->contracts->getRows())
        );
    }

    #[Route(path: '/inactive-customers', name: 'management_reports_inactive_customers', methods: ['GET'])]
    public function inactiveCustomers(Request $request): Response
    {
        $days = $this->inactiveDays($request);

        return $this->render('@ManagementReports/inactive_customers.html.twig', [
            'report_title' => 'management_reports.inactive_customers.title',
            'rows' => $this->inactiveCustomers->getRows($this->today(), $days),
            'days' => $days,
            'day_options' => self::INACTIVE_DAYS,
        ]);
    }

    #[Route(path: '/inactive-customers/export', name: 'management_reports_inactive_customers_export', methods: ['GET'])]
    public function inactiveCustomersExport(Request $request): Response
    {
        $days = $this->inactiveDays($request);
        $never = $this->translator->trans('management_reports.inactive_customers.never');

        return Csv::response(
            \sprintf('kimai_inactive_customers_%dd.csv', $days),
            [
                $this->translator->trans('customer'),
                $this->translator->trans('last_record'),
                $this->translator->trans('management_reports.inactive_customers.days_without_hours'),
            ],
            array_map(static fn (array $row): array => [
                $row['name'],
                $row['lastEntry']?->format('Y-m-d') ?? $never,
                $row['days'] ?? '',
            ], $this->inactiveCustomers->getRows($this->today(), $days))
        );
    }

    private function inactiveDays(Request $request): int
    {
        $days = $request->query->getInt('days', self::DEFAULT_INACTIVE_DAYS);

        return \in_array($days, self::INACTIVE_DAYS, true) ? $days : self::DEFAULT_INACTIVE_DAYS;
    }

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone($this->getUser()->getTimezone()));
    }
}
