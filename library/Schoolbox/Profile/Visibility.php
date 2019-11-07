<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Profile;

use External_UserInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\User;

class Visibility
{

    /**
     * @var External_UserInterface
     */
    private $externalUser;

    /**
     * @var IConfigLoader
     */
    private $config;

    /**
     * Constructor
     *
     * @param External_UserInterface $externalUser
     * @param IConfigLoader          $config
     */
    public function __construct(External_UserInterface $externalUser, IConfigLoader $config)
    {
        $this->externalUser = $externalUser;
        $this->config       = $config;
    }

    /**
     * Checks whether is viewee's email address is visible to viewer
     * @param User $viewee
     * @param User $viewer
     * @return bool
     */
    public function isEmailVisible(User $viewee, User $viewer): bool
    {
        if (!$viewee->getEmail()) {
            return false;
        }
        // A student can see another student's email address
        if ($viewee->getRole()->isStudent()) {
            return $viewer->getRole()->isStudent() || $this->isDetailVisible($viewee, $viewer);
        }

        return $this->isDetailVisible($viewee, $viewer);
    }

    /**
     * Checks whether is viewee's detail is visible to viewer
     * @param User $viewee
     * @param User $viewer
     * @return bool
     */
    public function isDetailVisible(User $viewee, User $viewer): bool
    {
        // Guest should never be able to see any other user's detail
        if ($viewer->getRole()->isGuest()) {
            return false;
        }

        // Only Superuser can see a guest
        if ($viewee->getRole()->isGuest()) {
            return $viewer->isSuperuser();
        }

        // Super user and Staff can see any user's detail
        // A viewer can see self
        if ($viewer->isSuperuser() || $viewer->getRole()->isStaff() || ($viewer->getId() === $viewee->getId())) {
            return true;
        }

        // Student can see staff's detail
        if ($viewer->getRole()->isStudent()) {
            return $viewee->getRole()->isStaff();
        }

        // Current user is a parent
        // a parent viewing a staff
        if ($viewee->getRole()->isStaff()) {
            return $this->config->get('report_staff_access');
        }

        // a parent viewing a student
        if ($viewee->getRole()->isStudent()) {
            // viewee is  viewer's enrolled student
            if (!$viewer->getExternalId()) {
                return false;
            }
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
            $exParent = $this->externalUser->getParent($viewee->getExternalId());
            return $exParent && $exParent['DirectoryIncludeFlag'];
        }

        // should not get here, but just in case
        return false;
    }
}
