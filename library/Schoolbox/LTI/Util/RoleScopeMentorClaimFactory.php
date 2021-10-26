<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use OAT\Library\Lti1p3Core\Role\RoleInterface;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\LTI\Claim\RoleScopeMentorClaim;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\User;

/**
 * Factory for building the role scope mentor claims for an assessment.
 *
 * The role scope mentor claim represents the users which the user launching a
 * resource link is considered a mentor for; generally, this will be a parent
 * or an instructor.
 *
 * @see https://www.imsglobal.org/spec/lti/v1p3/#role-scope-mentor-claims
 */
class RoleScopeMentorClaimFactory
{
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
     * Builds role scope mentor claims, given an assessment.
     *
     * * instructors are mentors for all non-excluded participants
     * * parents are mentors for all children who are non-excluded participants
     *
     * @param User        $user
     * @param IAssessable $assessment
     * @return RoleScopeMentorClaim|null A claim object, or null if it would've
     *                                   been empty.
     * @throws MapperException
     */
    public function buildForAssessment(User $user, IAssessable $assessment): ?RoleScopeMentorClaim
    {
        $mentoredUserIds = [];
        $folder          = $assessment->getBaseContext()->getFolder();
        if ($this->access->isInstructor($user, $folder)) {
            foreach ($assessment->getAllParticipants() as $participant) {
                if (!$participant->isExcluded()) {
                    $mentoredUserIds[] = $this->extractUserId($participant->getUser());
                }
            }
        } elseif ($user->getRole()->isParent()) {
            foreach ($user->getChildren() as $child) {
                $participant = $assessment->getParticipantIfExtant($child);
                if ($participant && !$participant->isExcluded()) {
                    $mentoredUserIds[] = $this->extractUserId($child);
                }
            }
        }
        $mentoredUserIds = array_filter(
            $mentoredUserIds,
            static function ($v) {
                return isset($v);
            }
        );
        return count($mentoredUserIds) > 0
            ? new RoleScopeMentorClaim(...$mentoredUserIds)
            : null;
    }

    /**
     * Extracts the user ID out of a user object.
     *
     * Currently, this is the external ID as this is what's most likely to be
     * shared; however this is subject to change.
     *
     * @param User $user
     * @return string|null
     */
    private function extractUserId(User $user): ?string
    {
        return $user->getExternalId();
    }
}
