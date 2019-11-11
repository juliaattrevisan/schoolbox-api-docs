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
     * Checks whether is viewee's detail is visible to viewer
     * @param User $viewee
     * @param User $viewer
     * @return bool
     */
    public function isSatisfiedBy(User $viewee, User $viewer): bool
    {
        /* Superusers see everything
         */
        if ($viewer->isSuperuser()) {
            return true;
        }

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
