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
use App\Security\RolePermissionManager;

/**
 * Whose timesheets a viewer may see in the hours summary.
 *
 * Admins see everyone. Anyone else (team leads) sees the users Kimai's own
 * rules allow: themselves, members of teams they lead, and team-less regular
 * users — the same check Kimai uses for its user reports.
 */
final class ReportScope
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RolePermissionManager $permissions,
    ) {
    }

    public function seesEveryone(User $viewer): bool
    {
        return $viewer->isSuperAdmin() || $viewer->isAdmin() || $viewer->canSeeAllData();
    }

    /**
     * Users the viewer may see, including disabled ones (their historical
     * hours still count). Sorted by display name.
     *
     * @return list<User>
     */
    public function visibleUsers(User $viewer): array
    {
        $everyone = $this->seesEveryone($viewer);
        $users = array_filter(
            $this->users->findAll(),
            fn (User $user): bool => $everyone || $this->permissions->checkUserAccess($user, $viewer, false)
        );

        usort($users, static fn (User $a, User $b): int => strcasecmp($a->getDisplayName(), $b->getDisplayName()));

        return $users;
    }

    /**
     * Ids to restrict the query to, or null when no restriction applies.
     *
     * @return list<int>|null
     */
    public function restrictToUserIds(User $viewer): ?array
    {
        if ($this->seesEveryone($viewer)) {
            return null;
        }

        return array_map(static fn (User $user): int => (int) $user->getId(), $this->visibleUsers($viewer));
    }
}
