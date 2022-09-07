<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Profile;

use External_UserInterface;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\IConfigLoader;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Class ValidDetailsVisible
 *
 * Spec pattern to determine Detail visibility; whether $viewer can see $viewee's details
 * on the profile screen such as Tutors, Houses, etc. based on role type, config settings
 * and other settings in the SIS.
 *
 * @package Schoolbox\Profile
 */
class ValidDetailsVisible
{
    /**
     * Constructor
     *
     * @param External_UserInterface $externalUser
     * @param IConfigLoader          $config
     */
    public function __construct(private External_UserInterface $externalUser, private IConfigLoader $config)
    {
    }

    /**
     * Checks whether is viewee's detail is visible to viewer
     * @param User $viewee
     * @param User $viewer
     * @return bool
     */
    public function isSatisfiedBy(User $viewee, User $viewer): bool
    {
        // Superusers see everything
        // A viewer can see self, if not guest
        if (
            $viewer->isSuperuser()
            || (!$viewer->getRole()->isGuest() && ($viewer->getId() === $viewee->getId()))
        ) {
            return true;
        }
        if (!$viewer->getRole()->isParent()) {
            return $this->isSatisfiedByRole($viewee->getRole()->getType(), $viewer->getRole(), $viewer->isSuperuser());
        }
        return $this->isSatisfiedByParent($viewee, $viewer);
    }

    /**
     * @param string $vieweeRoleType
     * @param Role   $viewerRole
     * @param bool   $isSuperUser
     * @return bool
     */
    public function isSatisfiedByRole(string $vieweeRoleType, Role $viewerRole, bool $isSuperUser): bool
    {
        if ($viewerRole->isParent()) {
            throw new Exception('Use isSatisfiedByParent instead');
        }

        // Guest should never be able to see any other user's detail
        if ($viewerRole->isGuest()) {
            return false;
        }

        // Only Superuser can see a guest
        if ($vieweeRoleType === Role::GUEST) {
            return $isSuperUser;
        }

        // Staff can see profile depending on role permission
        if ($viewerRole->isStaff()) {
            if ($vieweeRoleType === Role::STUDENT) {
                return $viewerRole->getViewStudentProfile();
            } elseif ($vieweeRoleType === Role::PARENT) {
                return $viewerRole->getViewParentProfile();
            } else {
                return true;
            }
        }

        // Student can see staff's detail
        if ($viewerRole->isStudent()) {
            return $vieweeRoleType === Role::STAFF;
        }

        // should not come here
        return false;
    }

    /**
     * @param User $viewee
     * @param User $viewer
     * @return bool
     */
    public function isSatisfiedByParent(User $viewee, User $viewer): bool
    {
        if (!$viewer->getRole()->isParent()) {
            throw new Exception('Use isSatisfiedByRole instead');
        }

        // Current user is a parent
        // a parent viewing a staff
        if ($viewee->getRole()->isStaff()) {
            return (bool)$this->config->get('report_staff_access');
        }

        // a parent viewing a student
        if ($viewee->getRole()->isStudent()) {
            /* Without an external id we can't really tell who you are
             */
            if (!$viewer->getExternalId()) {
                return false;
            }

            // viewee is viewer's enrolled student
            $externalChildren = $this->externalUser->getEnrolledStudents($viewer->getExternalId());
            return in_array($viewee->getExternalId(), array_column($externalChildren, 'RelatedID'));
        }

        // a parent viewing a parent
        if ($viewee->getRole()->isParent()) {
            // 'restrict_parent_emails' config is off and viewer's SIS contact flag is on (externalUser.DirectoryIncludeFlag)
            if ($this->config->get('restrict_parent_emails')) {
                return false;
            }

            if (!$viewee->getExternalId()) {
                return false;
            }

            /* Otherwise this is dependent on the DirectoryIncludeFlag value in the SIS
             * which determines "public-ness" of a parent's information
             */
            $exParent = $this->externalUser->getParent($viewee->getExternalId());

            return $exParent && $exParent['DirectoryIncludeFlag'];
        }

        // should not get here, but just in case
        return false;
    }
}
