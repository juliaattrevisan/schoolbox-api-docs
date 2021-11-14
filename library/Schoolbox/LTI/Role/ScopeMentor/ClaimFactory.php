<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Role\ScopeMentor;

use Alaress\Codec\ICodec;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
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
class ClaimFactory
{
    /**
     * Access permission checker, used for checking if the given user is an
     * instructor for a given assessment.
     */
    private Access $access;

    /**
     * User codec, used for encoding a user into the claim.
     */
    private ICodec $userCodec;

    /**
     * Constructor
     *
     * @param Access $access
     * @param ICodec $userCodec
     */
    public function __construct(Access $access, ICodec $userCodec)
    {
        $this->access    = $access;
        $this->userCodec = $userCodec;
    }

    /**
     * Builds role scope mentor claims, given an assessment.
     *
     * * instructors are mentors for all non-excluded participants
     * * parents are mentors for all children who are non-excluded participants
     *
     * @param User        $user
     * @param IAssessable $assessment
     * @return Claim|null A claim object, or null if it would've been empty.
     * @throws MapperException
     */
    public function buildForAssessment(User $user, IAssessable $assessment): ?Claim
    {
        $mentoredUserIds = [];
        $folder          = $assessment->getBaseContext()->getFolder();
        if ($this->access->isInstructor($user, $folder)) {
            foreach ($assessment->getAllParticipants() as $participant) {
                if (!$participant->isExcluded()) {
                    $mentoredUserIds[] = $this->userCodec->encode($participant->getUser());
                }
            }
        } elseif ($user->getRole()->isParent()) {
            foreach ($user->getChildren() as $child) {
                $participant = $assessment->getParticipantIfExtant($child);
                if ($participant && !$participant->isExcluded()) {
                    $mentoredUserIds[] = $this->userCodec->encode($child);
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
            ? new Claim(...$mentoredUserIds)
            : null;
    }
}
