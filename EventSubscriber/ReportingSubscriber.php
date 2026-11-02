<?php

declare(strict_types=1);

/*
 * This file is part of the "ManagementReportsBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ManagementReportsBundle\EventSubscriber;

use App\Event\ReportingEvent;
use App\Reporting\Report;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ReportingSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReportingEvent::class => ['onReporting', 100],
        ];
    }

    public function onReporting(ReportingEvent $event): void
    {
        if ($this->security->isGranted('management_reports')) {
            $event->addReport(new Report('management_summary', 'management_reports_summary', 'management_reports.summary.title', 'reporting'));
        }

        if ($this->security->isGranted('management_reports_admin')) {
            $event->addReport(new Report('management_contracts', 'management_reports_contracts', 'management_reports.contracts.title', 'users'));
            $event->addReport(new Report('management_inactive_customers', 'management_reports_inactive_customers', 'management_reports.inactive_customers.title', 'customer'));
        }
    }
}
