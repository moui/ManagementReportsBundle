<?php

declare(strict_types=1);

/*
 * This file is part of the "ManagementReportsBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ManagementReportsBundle\DependencyInjection;

use App\Plugin\AbstractPluginExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class ManagementReportsExtension extends AbstractPluginExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    /**
     * `management_reports` (hours summary) goes to the roles that already see
     * other people's timesheets; each team lead's rows are then narrowed to the
     * users Kimai lets them see. `management_reports_admin` (work contracts,
     * inactive customers) shows the whole organisation, so it is admin-only.
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'roles' => [
                    'ROLE_TEAMLEAD' => ['management_reports'],
                    'ROLE_ADMIN' => ['management_reports', 'management_reports_admin'],
                    'ROLE_SUPER_ADMIN' => ['management_reports', 'management_reports_admin'],
                ],
            ],
        ]);
    }

    public function getAlias(): string
    {
        return 'management_reports';
    }
}
