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
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

/**
 * Class CanSeeAssessment
 *
 * In $params we need:
 *  - 'activity' IAssessable:
 *      - context hierarchy fully hydrated
 *      - participation fully hydration
 *
 * The rules are:
 *  - if you have WRITE access to the folder, you can see this assessment;
 *  - else
 *      - if not yet published, FALSE
 *      - if participant, TRUE
 *      - if parent access permitted AND a child is a participant, TRUE
 *      - else FALSE
 *
 *
 * @package Schoolbox\Learning\Authorizer\Rule
 */
class CanSeeAssessment implements IAuthorizationRule
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
     * @throws \Alaress\Authorizer\Failure\Base if the request isn't authorized.
     * @return bool
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (empty($params['assessment'])) {
            if (empty($params['participant'])) {
                throw new InvalidArgumentException("Cannot check auth, need an assessable param of 'assessment' or participant of 'participant'");
            }

            $activity = $params['participant']->getAssessable();
        } else {
            $activity = $params['assessment'];
        }

        if (!$activity instanceof IAssessable) {
            throw new InvalidArgumentException("Cannot check auth, need an assessable param of 'assessment' or participant of 'participant'");
        }

        $folderId = $activity->getBaseContext()->getFolder()->getId();

        if (!$folderId) {
            throw new InvalidArgumentException("Cannot check auth, activity does not have folder hydrated");
        }

        $access = $this->access->getFolderAccess($user, [ $folderId ]);

        if ($access >= UserAccess::ACCESS_WRITE) {
            return true;
        }

        if ($activity->getPublishAt()->isFuture()) {
            throw new Unauthorized();
        }

        if ($activity->isParticipant($user)) {
            return true;
        }

        // from here on, we've exhausted all non-parent tests
        if (!$user->getRole()->isParent()) {
            throw new Unauthorized();
        }

        if ($this->conf->get('restrict_parent_duework_access') == 0) {
            throw new Unauthorized();
        }

        // if we got passed a participant there's one last check we can do...
        if (isset($params['participant']) ) {
            $learner = $params['participant']->getUser();
        }
        else if (isset($params['student'])) {
            $learner = $params['student'];
        }

        if($learner instanceof User) {
            // ...after we magically hit an external database
            $learner->loadParents();
            if ($user->isParentOf($learner)) {
                return true;
            }
        }

        throw new Unauthorized();
    }

}
