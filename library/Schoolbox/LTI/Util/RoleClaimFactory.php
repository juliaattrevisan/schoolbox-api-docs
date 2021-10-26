<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use OAT\Library\Lti1p3Core\Role\RoleInterface;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\User;

/**
 * Factory for building various role claims.
 */
class RoleClaimFactory
{
    public const INSTITUTION_ROLE_STAFF   = RoleInterface::NAMESPACE_INSTITUTION . '#Staff';
    public const INSTITUTION_ROLE_STUDENT = RoleInterface::NAMESPACE_INSTITUTION . '#Student';
    public const INSTITUTION_ROLE_GUEST   = RoleInterface::NAMESPACE_INSTITUTION . '#Guest';
    public const INSTITUTION_ROLE_OTHER   = RoleInterface::NAMESPACE_INSTITUTION . '#Other';

    public const MEMBERSHIP_ROLE_INSTRUCTOR = RoleInterface::NAMESPACE_CONTEXT . '#Instructor';
    public const MEMBERSHIP_ROLE_LEARNER    = RoleInterface::NAMESPACE_CONTEXT . '#Learner';
    public const MEMBERSHIP_ROLE_MENTOR     = RoleInterface::NAMESPACE_CONTEXT . '#Mentor';

    /**
     * Access permission checker, used for checking if the given user is an
     * instructor for a given assessment.
     */
    private Access $access;

    /**
     * Constructor
     *
     * @param Access $access
     */
    public function __construct(Access $access)
    {
        $this->access = $access;
    }

    /**
     * Build institution-level role claims.
     *
     * These claims are relevant to the entire institution, and will correspond
     * to `$user`'s role type.
     *
     * @param User $user
     * @return array
     */
    public function buildInstitutionRoles(User $user): array
    {
        $roles = [];
        if ($user->getRole()->isStaff()) {
            $roles[] = self::INSTITUTION_ROLE_STAFF;
        } elseif ($user->getRole()->isStudent()) {
            $roles[] = self::INSTITUTION_ROLE_STUDENT;
        } elseif ($user->getRole()->isParent()) {
            $roles[] = self::INSTITUTION_ROLE_OTHER;
        } elseif ($user->getRole()->isGuest()) {
            $roles[] = self::INSTITUTION_ROLE_GUEST;
        }
        return $roles;
    }

    /**
     * Build context-level role claims, given a folder.
     *
     * @param User  $user
     * @param Model $folder
     * @return array
     * @throws MapperException if finding whether `$user` is a learner or
     *                         instructor goes wrong
     */
    public function buildContextRolesForFolder(User $user, Model $folder): array
    {
        $roles = [];
        if ($this->access->isInstructor($user, $folder)) {
            $roles[] = self::MEMBERSHIP_ROLE_INSTRUCTOR;
        } elseif ($this->access->isLearner($user, $folder)) {
            $roles[] = self::MEMBERSHIP_ROLE_LEARNER;
        }
        return $roles;
    }

    /**
     * Builds context-level role claims, given an assessment.
     *
     * In addition to the roles for the assessment's folder:
     * * instructors have the Mentor role so long as the assessment has
     *   non-excluded participants
     * * parents have the Mentor role so long as one of their children is a
     *   non-excluded participant in the assessment
     *
     * @param User        $user
     * @param IAssessable $assessment
     * @return array
     * @throws MapperException
     */
    public function buildContextRolesForAssessment(User $user, IAssessable $assessment): array
    {
        $folder = $assessment->getBaseContext()->getFolder();
        $roles  = $this->buildContextRolesForFolder($user, $folder);
        if ($this->access->isInstructor($user, $folder)) {
            foreach ($assessment->getAllParticipants() as $participant) {
                if (!$participant->isExcluded()) {
                    $roles[] = self::MEMBERSHIP_ROLE_MENTOR;
                    break;
                }
            }
        } elseif ($user->getRole()->isParent()) {
            foreach ($user->getChildren() as $child) {
                $participant = $assessment->getParticipantIfExtant($child);
                if ($participant && !$participant->isExcluded()) {
                    $roles[] = self::MEMBERSHIP_ROLE_MENTOR;
                    break;
                }
            }
        }
        return $roles;
    }
}
