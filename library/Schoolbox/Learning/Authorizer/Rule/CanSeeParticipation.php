<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Failure\Unauthorized;
use InvalidArgumentException;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

/**
 * In $params we need:
 *  - 'activity' IAssessable:
 *      - context hierarchy fully hydrated
 *      - participation fully hydration
 * - a 'student' or 'participant'
 *
 * The rules in order of preference:
 *  - In all cases, if the specified participant user is not a participant, FALSE
 *  - if you have WRITE access to the folder TRUE
 *  - else
 *      - if not yet published, FALSE
 *      - if participant, TRUE
 *      - if parent access permitted, and system configured to let parents see dueWork details AND a child is a participant and child could see the assessment, TRUE
 *      - else FALSE
 *
 * Note this does not permit non-participants with WRITE+ perm to see this page. That is outside this rule's responsibility.
 */
class CanSeeParticipation extends AbstractParticipationRule
{

    /** @var Access */
    private $access;

    /** @var bool */
    private $restrictStudentSubmissions;


    /**
     * @param Access $access
     * @param bool   $restrictStudentSubmissions
     */
    public function __construct(Access $access, bool $restrictStudentSubmissions)
    {
        $this->access                     = $access;
        $this->restrictStudentSubmissions = $restrictStudentSubmissions;
    }


    /**
     * Performs an authorization check.
     *
     * @inheritdoc
     * @throws InvalidArgumentException If unable to find a student and assessable in route params
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (null === $user) {
            throw new InvalidArgumentException("Cannot check auth, user not provided");
        }

        $assessable  = $this->findAssessable($params);
        $student     = $this->findStudent($params);
        $participant = $assessable->getParticipantIfExtant($student);
        $accessLevel = $this->calculateAccessLevel($assessable, $user);

        // All other exclusions mean this page does not exist
        if (!$participant) {
            // Then this route does not exist.
            throw new InvalidPath();
        }

        // Instructors or staffs can always see participants' summary pages
        if ($accessLevel >= UserAccess::ACCESS_WRITE || $user->getRole()->isStaff()) {
            return true;
        }

        // Is assessment not yet published for participant?
        if ($participant->getPublishDate()->isFuture()) {
            throw new Unauthorized();
        }

        // Is student viewing own participation?
        if ((int)$user->getId() === (int)$participant->getUser()->getId()) {
            // TODO remove casts once SA-4539 is fixed/merged
            return true;
        }
        // from here on, we've exhausted all non-parent tests
        if (!$user->getRole()->isParent()) {
            throw new Unauthorized();
        }

        // Hide just the detail of work from parents
        if ($this->restrictStudentSubmissions) {
            throw new Unauthorized();
        }

        $student->loadParents();

        if ($user->isParentOf($student)) {
            return true;
        }

        throw new Unauthorized();
    }


    /**
     * Returns the user's access level to the assessments base context's folder
     *
     * @param IAssessable $assessable
     * @param User        $user
     * @return int
     * @throws InvalidArgumentException
     */
    private function calculateAccessLevel(IAssessable $assessable, User $user)
    {
        $folderId = $assessable->getBaseContext()->getFolder()->getId();
        if (!$folderId) {
            throw new InvalidArgumentException("Cannot check auth, activity does not have folder hydrated");
        }
        return $this->access->getFolderAccess($user, $folderId);
    }
}
