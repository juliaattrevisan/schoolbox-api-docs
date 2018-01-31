<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;


use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use InvalidArgumentException;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
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
 *  - if you have WRITE access to the folder, you can see the participation
 *  - else
 *      - if not yet published, FALSE
 *      - if participant, TRUE
 *      - if parent access permitted AND a child is a participant, TRUE
 *      - else FALSE
 */
class CanSeeParticipation implements IAuthorizationRule
{

    /** @var Access */
    private $access;

    /** @var IConfigLoader */
    private $conf;


    /**
     * @param Access        $access
     * @param IConfigLoader $conf
     */
    public function __construct(Access $access, IConfigLoader $conf)
    {
        $this->access = $access;
        $this->conf   = $conf;
    }


    /**
     * Performs an authorization check.
     *
     * @param  array $params Any parameters passed as part of the route.
     * @param  User  $user   The user to authorize, if any. Null value assumes
     *                       that no user is logged in.
     * @return bool
     * @throws \Alaress\Authorizer\Failure\Base if the request isn't authorized.
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
            return false;
        }

        $folderId = $assessable->getBaseContext()->getFolder()->getId();
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
        if ($this->conf->get('restrict_student_submissions')) {
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


    /**
     * @param array $params
     * @return IAssessable
     * @throws InvalidArgumentException If unable to locate an assessable instance in route params
     */
    private function findAssessable(array $params): IAssessable
    {
        if ($params['assessment'] instanceof IAssessable) {
            return $params['assessment'];
        }
        if ($params['participant'] instanceof Participant) {
            return $params['participant']->getAssessable();
        }
        throw new InvalidArgumentException('Unable to locate an assessable instance in route params');
        // TODO: Log to sentry
    }


    /**
     * @param array $params
     * @return User
     * @throws InvalidArgumentException If unable to locate a student User instance in route params
     */
    private function findStudent(array $params): User
    {
        if ($params['student'] instanceof User) {
            return $params['student'];
        }
        if ($params['participant'] instanceof Participant) {
            return $params['participant']->getUser();
        }
        throw new InvalidArgumentException('Unable to locate an student User instance in route params');
        // TODO: Log to sentry
    }
}
