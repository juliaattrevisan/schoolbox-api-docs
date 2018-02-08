<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Failure\Unauthorized;
use InvalidArgumentException;
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
 * The rules are:
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
        $assessable  = $this->findAssessable($params);
        $student     = $this->findStudent($params);
        $participant = $assessable->getParticipantIfExtant($student);

        // Is student not a participant?
        if (!$participant) {
            // Then this route does not exist.
            throw new InvalidPath();
        }

        $folderId   = $assessable->getBaseContext()->getFolder()->getId();
        if (!$folderId) {
            throw new InvalidArgumentException("Cannot check auth, activity does not have folder hydrated");
        }
        if (null === $user) {
            throw new InvalidArgumentException("Cannot check auth, user not provided");
        }

        $access = $this->access->getFolderAccess($user, $folderId);

        if ($access >= UserAccess::ACCESS_WRITE) {
            // Instructors can always see participants' summary pages
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

        // if we got passed a participant there's one last check we can do...
        if ($access >= UserAccess::ACCESS_READ) {
            // ...after we magically hit an external database
            $student->loadParents();
            if ($user->isParentOf($student)) {
                return true;
            }
        }

        throw new Unauthorized();
    }
}
